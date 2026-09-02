#!/usr/bin/env bash
#
#  Why search by meaning is returning the wrong pictures.
#
#  Read-only. It answers three questions the code cannot answer on its own,
#  because they are properties of this library rather than of the algorithm:
#
#    1. Are the stored embeddings all the same width? The projection maps any
#       width down to 64 by averaging adjacent components, so band 3 of a
#       768-wide vector and band 3 of a 1536-wide one describe different parts
#       of the space. Mixed widths compared against each other are noise, and
#       nothing in the search filters by width.
#
#    2. Do rows actually have a projection? A row without one is skipped
#       entirely, so a half-converted library searches a fraction of itself.
#
#    3. What do the similarity scores actually look like against the 0.22
#       floor? A floor calibrated for full-embedding similarity is the wrong
#       floor for projected similarity, and would either pass everything or
#       nothing.
#
#  Run through the box-diagnose workflow, which holds the key.
set -euo pipefail

cd /var/www/wp 2>/dev/null || { echo "no WordPress at /var/www/wp" >&2; exit 1; }

WP="wp --allow-root --skip-plugins --skip-themes"
PREFIX="$( $WP db prefix 2>/dev/null || echo wp_ )"
T="${PREFIX}vergeml_ai_index"

echo "=== index table: ${T}"
$WP db query "SELECT COUNT(*) AS rows_total FROM ${T};" --skip-column-names 2>/dev/null \
  | sed 's/^/rows total            /'

echo
echo "=== embedding widths in use  (more than one line here is the bug)"
$WP db query "SELECT COALESCE(embedding_dims,0) AS width, COUNT(*) AS n FROM ${T} GROUP BY embedding_dims ORDER BY n DESC;" 2>/dev/null

echo
echo "=== how many rows are searchable at all"
$WP db query "SELECT
    SUM(error = '')                                      AS described_ok,
    SUM(error = '' AND projection IS NOT NULL)           AS has_projection,
    SUM(error = '' AND projection IS NULL)                AS missing_projection,
    SUM(error = '' AND embedding IS NOT NULL)            AS has_full_embedding
  FROM ${T};" 2>/dev/null

echo
echo "=== model and prompt spread (a change here explains a width change)"
$WP db query "SELECT model, model_version, embedding_dims, COUNT(*) AS n FROM ${T} GROUP BY model, model_version, embedding_dims ORDER BY n DESC LIMIT 8;" 2>/dev/null

echo
echo "=== projection byte length  (64 floats packed = 256 bytes; anything else is a different width)"
$WP db query "SELECT LENGTH(projection) AS bytes, COUNT(*) AS n FROM ${T} WHERE projection IS NOT NULL GROUP BY LENGTH(projection) ORDER BY n DESC LIMIT 6;" 2>/dev/null
