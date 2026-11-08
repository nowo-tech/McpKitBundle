# FrankenPHP worker audit (kernel not reset)

The bundle keeps no state between requests: services are readonly, configuration is compiled into the container and
per-request data lives on the `Request`. Igor (`composer igor`) and `nowo-tech/phpstan-frankenphp` run in CI.
