# EVAL Isolated HTTPS Runtime

Development/test only: real Laravel 13.35.0 Application, HTTP Kernel and Router behind a Node HTTPS loopback proxy, reusing the existing local authentication and academic adapters. Explicit minimal bootstrap/configuration/providers; not a complete institutional application or production web-server configuration.

Run from the repository root using the established owned MySQL runtime:

```powershell
./backend/scripts/local.ps1 -Command test -Unit U11 -Arguments @('--filter=LocalHttpsRuntimeTest')
./backend/scripts/local.ps1 -Command test -Unit U11
```

The test creates temporary certificate/private-key material, verifies TLS using that certificate only in its request context, launches PHP and Node only on 127.0.0.1 ephemeral ports and terminates only its owned process resources in teardown. PHP receives restricted runtime database credentials; migration credentials are stripped. Node receives no database credentials or session-encryption key. Temporary files are removed after worker shutdown. No public listener, firewall change, global certificate trust or persistent manual server is established.

`https-proxy.mjs` rejects unexpected Host, forwarding headers, reserved proxy headers and method override. Upstream requests carry a server-owned per-run secret. `router.php` rejects direct upstream access without that secret and the isolated U11/runtime configuration; TLS/origin are reconstructed only from server configuration, never client forwarding headers. No standard trusted-proxy headers are enabled. HTTPS authentication still verifies configured origin, cookies, CSRF and current credentials/roles. No request automatically replays.

Laravel uses explicit providers/config and an empty package discovery manifest, avoiding generated repository caches, dotenv/global configuration and unselected application scaffolding. Cookie/session handling stays with the verified adapter; a second framework session/CSRF layer is not introduced. Unexpected runtime/kernel errors return sanitized 503 with a correlation ID and automatic_retry=false; SQL, exception traces and credentials are not returned. Detailed diagnostic reporting is intentionally not established in this isolated harness; production observability remains future work.

Persisted reference reads are server-bound; institutional identity labels remain unavailable. Eligible submission history therefore returns reference_unavailable rather than invented names. No public U10/U11 writer, UI or synthetic institutional profile is activated. Synthetic account credentials and fixture data are test-only.

Actual TLS, session lifecycle and kernel routing tests do not prove browser behavior, certificate trust on school clients, offline school assets, physical LAN/WLAN operation, throughput, deployment or complete task 8.2 acceptance.
