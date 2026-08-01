# Autolex production deploy observability

A `main` ágra kerülő változások automatikusan elindítják a validációt és a cPanel production deployt.

A `Report Autolex Production Deploy` workflow a futás lezárása után ellenőrzi:

- a `Validate and build` job eredményét;
- a `Deploy to cPanel` job eredményét;
- az élő `/wp-json/autolex/v1/status` végpontot;
- az elvárt és az élő pluginverzió egyezését.

A bizonyíték az Issue #34-ben jelenik meg. Hibánál a riport workflow fail-closed módon sikertelenre vált.
