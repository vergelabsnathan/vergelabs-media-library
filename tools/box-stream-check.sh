# One streamed guide turn against the production service, from the box, with
# the box's own licence: mint a session token, open the conversation, count
# what came back and when. Spends two metered guide turns and one model call
# (a few cents). Prints no key and no token.
#
#     ssh root@box 'SERVICE=https://ai.vergelabs.nl/v1 bash -s' < tools/box-stream-check.sh
set -e
cd /var/www/wp
SERVICE="${SERVICE:-https://ai.vergelabs.nl/v1}"
T=/tmp/vgml-stream-check
rm -rf "$T" && mkdir -p "$T" && chmod 700 "$T"

# The bodies, written by PHP so the key never crosses a shell argument.
wp eval '
  $s = vergeml_ai_settings();
  $key = vergeml_ai_unseal( $s["license_key"] );
  $summary = vergeml_guide_summary();
  $current = isset( $summary["folders"] ) ? array_values( (array) $summary["folders"] ) : array();
  file_put_contents( "'"$T"'/session.json", wp_json_encode( array( "license_key" => $key, "site" => home_url(), "summary" => $summary ) ) );
  file_put_contents( "'"$T"'/stream.json", wp_json_encode( array( "conversation" => array(), "tree" => array( "folders" => array(), "tags" => array() ), "input" => array( "open" => true ), "summary" => $summary, "current" => $current ) ) );
  echo "summary: ", strlen( wp_json_encode( $summary ) ), " bytes, ", count( $current ), " folders now\n";
' --allow-root --skip-themes 2>&1 | grep -v "^Deprecated:"

# 1. the token
curl -s -o "$T/session.out" -w "session: HTTP %{http_code} in %{time_total}s\n" \
  -H "Content-Type: application/json" --data-binary @"$T/session.json" "$SERVICE/guide/session"
TOKEN=$(php -r '$j = json_decode( file_get_contents( "'"$T"'/session.out" ), true ); echo isset( $j["token"] ) ? $j["token"] : "";')
php -r '$j = json_decode( file_get_contents( "'"$T"'/session.out" ), true ); echo isset( $j["expires_at"] ) ? "token expires " . $j["expires_at"] . "\n" : "no token: " . file_get_contents( "'"$T"'/session.out" ) . "\n";'
if [ -z "$TOKEN" ]; then rm -rf "$T"; exit 1; fi

# 2. the stream: first byte vs last byte says whether it streamed
curl -sN -o "$T/stream.out" -w "stream: HTTP %{http_code}, %{size_download} bytes, first byte at %{time_starttransfer}s, done at %{time_total}s\n" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" --data-binary @"$T/stream.json" "$SERVICE/guide/stream"

echo "events: $(grep -c '^event: ' "$T/stream.out")  say $(grep -c '^event: say' "$T/stream.out")  tree $(grep -c '^event: tree' "$T/stream.out")  done $(grep -c '^event: done' "$T/stream.out")  error $(grep -c '^event: error' "$T/stream.out")"
php -r '
  $lines = file( "'"$T"'/stream.out", FILE_IGNORE_NEW_LINES );
  $said = ""; $tree = null; $done = null; $err = null; $type = "";
  foreach ( $lines as $l ) {
    if ( strpos( $l, "event: " ) === 0 ) { $type = substr( $l, 7 ); continue; }
    if ( strpos( $l, "data: " ) !== 0 ) continue;
    $d = json_decode( substr( $l, 6 ), true );
    if ( $type === "say" ) $said .= $d["text"];
    if ( $type === "tree" ) $tree = $d["tree"];
    if ( $type === "done" ) $done = $d;
    if ( $type === "error" ) $err = $d;
  }
  echo "said (", strlen( $said ), " chars): ", str_replace( "\n", " / ", mb_substr( $said, 0, 400 ) ), "\n";
  echo "tree: ", $tree === null ? "none" : count( $tree["folders"] ) . " folders, " . count( $tree["tags"] ) . " tags", "\n";
  echo "done: ", $done === null ? "none" : "usage " . json_encode( $done["usage"] ) . ", chips " . json_encode( $done["choices"] ), "\n";
  if ( $err !== null ) echo "error: ", json_encode( $err ), "\n";
  echo "fence in words: ", strpos( $said, "```" ) === false ? "no" : "YES", "\n";
'
rm -rf "$T"
