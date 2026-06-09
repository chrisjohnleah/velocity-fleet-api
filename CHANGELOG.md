# Changelog

All notable changes to this project are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.1.2] - 2026-06-09

### Added
- Bounded HTTP timeouts on `VelocityFleetConnector` (`connect_timeout` 10s, `timeout` 30s) — Guzzle defaults to no timeout, so a stalled connection could otherwise hang a request (and any caller's lock/job) indefinitely.

## [0.1.1] - 2026-06-09

### Added
- `ApiException::header()` — case-insensitive lookup of a response header from a failed request.
- `ApiException::retryAfter()` — the wait, in seconds, parsed from the `Retry-After` header (RFC 9110: both delay-seconds and HTTP-date forms), so callers can back off correctly on a `429`.

## [0.1.0] - 2026-06-09

### Added
- Initial read SDK for the Radius Velocity Fleet API, built on Saloon v4.
- `VelocityFleetConnector`: Bearer auth, trailing-slash-safe endpoints, and exponential backoff on connection errors / 429 / 5xx.
- `VelocityFleet` high-level client: static API-token auth (`withToken`), third-party refresh-token auth (`withRefreshToken`), an OAuth2 `refresh_token` exchange, proactive refresh before a known expiry, and reactive refresh-and-retry on a 401.
- `TokenStore` contract with an in-memory `ArrayTokenStore`, plus a `StoredToken` value object.
- Typed DTOs: `Customer`, `DevicePositions`, `DeviceGroup`, and `Device` (with `ignitionOn()` / `occurredAt()` helpers).
- Resources: `customers()->list()` and `devicePositions()->forCustomer()` / `->devices()`.
- Typed exception hierarchy: `VelocityFleetException`, `NotConnectedException`, `AuthenticationException`, `ApiException` (with `status`, `body`, `headers`).

[Unreleased]: https://github.com/chrisjohnleah/velocity-fleet-api/compare/v0.1.2...main
[0.1.2]: https://github.com/chrisjohnleah/velocity-fleet-api/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/chrisjohnleah/velocity-fleet-api/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/chrisjohnleah/velocity-fleet-api/releases/tag/v0.1.0
