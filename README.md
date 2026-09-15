<div align="center" style="text-align: center;">

![NAF](assets/naf-logo-small-square.png)

[![NAF Session Plugin](https://github.com/nafphp/session/actions/workflows/php.yml/badge.svg)](https://github.com/nafphp/session/actions/workflows/php.yml)

</div>

[← Back to NAF](https://github.com/nafphp/framework)

---

# naf/session

> **Simple session management for NAF, with flash message support built-in.**

This plugin adds a lightweight session layer to your NAF app, starts sessions safely in HTTP requests, and exposes helpers so you can store data (including flash messages) without worrying about headers or manual initialization.

> 🧩 Part of the official NAF plugin collection.
> Install it when you need session persistence, and nothing else.

## Documentation

**[Sessions →](https://nafphp.github.io/docs/sessions/)**

Everything about this package — what it does, how it is configured and what it needs — lives
in the [NAF documentation](https://nafphp.github.io/docs/). Not sure which packages you need?
[Start here](https://nafphp.github.io/docs/choosing-packages/).

## Install

```bash
composer require naf/session
```

## License

MIT. Part of [NAF](https://github.com/nafphp/framework).


## Unreleased Nafinity integration candidate

Target branch: `v0.2.2-rc`. This behavior is not a published release yet.

Repeated session-table setup preserves PostgreSQL and SQLite transactions when indexes already exist.

The database-session migration supports PostgreSQL, MySQL/MariaDB and SQLite. MariaDB session updates use separately bound update values instead of unsupported row-alias syntax. The database backend is tested through insert, repeated write, read and destroy against both application engines.

The real-engine regression is `tests/database-host.php`. In the disposable Nafinity
Compose test host, run it with `NAF_SESSION_TEST_BOOTSTRAP=/workspace/app/bootstrap.php`.
It requires `APP_ENV=test` and `DB_DATABASE=nafinity_test`, creates/validates the session
schema and exercises native insert/update/read/destroy with an isolated random session ID.
Run once with MariaDB and once with the PostgreSQL test connection.
