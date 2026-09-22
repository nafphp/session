# Working on naf/session

NAF is a small PHP framework with optional Composer plugins. Its core owns boot,
configuration, the service container, routing, events and PSR-7 responses. Prefer existing
NAF helpers, services and extension interfaces; keep application business rules in the host.
This package declares `type: naf-plugin` and is discovered after installation in a NAF host.
The plugin repository itself is not the application's web root.

Before changing code, read the [shared contribution workflow](https://github.com/nafphp/docs/blob/main/AGENT_WORKFLOW.md)
and [release procedure](https://github.com/nafphp/docs/blob/main/RELEASING.md).
In the multi-repository workspace, the same documents are in the sibling `docs/` checkout;
use the linked copies when working from a standalone clone. Preserve other contributors' work.
Review and update user documentation with every behavior change. Source fixes use an RC branch;
verified documentation-only changes can be merged and published by the agent.

## What this plugin does

`naf/session` wraps session lifecycle, values and flash messages, with optional database storage.
Install with `composer require naf/session`. The host's `session` configuration selects storage
and trusted proxy behavior. Auth and form/CSRF plugins reuse this session rather than keeping
independent state.

## Use it

With a bootstrapped host (explicit `start()` is useful in CLI checks):

```php
<?php
use function Naf\Session\session;

session()->start();
session()->set('locale', 'de');
$locale = session()->get('locale', 'en');
session()->flash('notice', 'Saved');
$notice = session()->getFlash('notice');
```

A flash value is consumed by `getFlash()`. Use `forget()` for one key and `clear()` for the
session; inspect those semantics before changing authentication cleanup. `regenerate()` has
an interval parameter, so do not assume a default call always rotates immediately.

## Change it here

[Session](src/Core/Session.php), [bootstrap](bootstrap.php), [config](src/config.php),
[storage handlers](src/Storage/) and [migrations](src/Migrations/) are the entry points.
Database storage additionally needs `naf/database`, its configured connection, and migrations.
Keep session start before output. Preserve cookie security, ID rotation, expiration and
proxy trust; forwarded headers are not trustworthy merely because a client sent them.

## Verify

Run `composer test` and `composer validate --strict`. Use [tests](tests/) and temporary storage.
Check values/flash, rotation, cleanup, cookies, trusted/untrusted proxies and the selected
storage handler. HTTP behavior must be checked across requests with an actual cookie jar.
No `analyse` script is declared.

User docs: [Sessions](https://nafphp.github.io/docs/sessions/).

Follow the shared [PHP code style](https://github.com/nafphp/docs/blob/main/CODE_STYLE.md)
and `.php-cs-fixer.dist.php`. Run `composer style:check`; `composer style:fix` applies the rules.
Keep logical steps and local names readable, preserving public signatures and template output.

## Boot order

`extra.naf.boot.after` places Session after `naf/database` when that optional plugin
is installed. Session starts during HTTP boot, and database-backed storage needs the
database service at that point. Composer `suggest` still describes installation.
