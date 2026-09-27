# Legacy game hosting — 2.0.0

The former CDN/reverse-proxy instructions are retired.

Legacy games load from the operator's own `/games/ExactGameName/` folder.
Obtain authorized assets separately, extract them locally and preserve the exact
folder name. No .htaccess CDN switch or reverse proxy is required.

Protected CEDAR games are a separate licensed service. Their hosted assets and
engines are not part of legacy packs or the customer application.

See [INSTALL.md](INSTALL.md) and [PATCHES.md](PATCHES.md). Existing legacy folders
without patch history start at 2.0.0; updating one game does not advance others.
