"""
build-broken.py <source.zip> <slug> <old> <new> <out.zip>

A release that fatals on load, for the pull-back rehearsal in
docs/runbooks/rollback.md. Every file of the real release is kept; only the
main file changes: the two version lines, and one call to a function that
does not exist, right after the version define, so the header is readable
and the plugin dies the moment WordPress includes it.
"""
import io, re, sys, zipfile

src, slug, old, new, out = sys.argv[1:6]
main = f"{slug}/{slug}.php"

with zipfile.ZipFile(src) as zin, zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as zout:
    names = zin.namelist()
    assert main in names, f"{main} not in {src}"
    for info in zin.infolist():
        data = zin.read(info.filename)
        if info.filename == main:
            text = data.decode("utf-8")
            n_header = len(re.findall(rf"^(\s*\*?\s*Version:\s*){re.escape(old)}\s*$", text, flags=re.M))
            assert n_header == 1, f"expected one 'Version: {old}' header line, found {n_header}"
            text = re.sub(rf"^(\s*\*?\s*Version:\s*){re.escape(old)}\s*$", rf"\g<1>{new}", text, flags=re.M)
            define_re = re.compile(rf"^(.*define\(\s*'[A-Z_]+_VERSION'\s*,\s*')" + re.escape(old) + r"('\s*\);.*)$", re.M)
            assert len(define_re.findall(text)) == 1, "expected one version define"
            marker = (
                f"\n// {new}: the pull-back rehearsal build (docs/runbooks/rollback.md). It fatals on purpose.\n"
                f"vergeml_rehearsal_release_{new.replace('.', '_')}_fatals_here();\n"
            )
            text = define_re.sub(rf"\g<1>{new}\g<2>" + marker.replace("\\", "\\\\"), text)
            assert f"Version: {new}" in text and f"'{new}'" in text and "_fatals_here();" in text
            data = text.encode("utf-8")
        zout.writestr(info, data)

print(f"{out}: {len(names)} files, {main} at {new}, fatal inserted")
