# Security Policy

## Supported versions

| Version | Supported |
|---------|-----------|
| 1.x     | Yes       |

## Reporting a vulnerability

Please report vulnerabilities privately by email to thunder@vanqard.com, or by
opening a [private security advisory](https://github.com/vanqard/van-storm/security/advisories/new)
on GitHub. Do not open a public issue. You should receive a reply within a week.

## Security notes for users

- Criteria keys and `orderBy` fields passed to the finder methods are checked against the
  entity's mapped columns, or else must be plain SQL identifiers, and are always quoted.
  Values are always bound as parameters. Raw SQL that you build yourself with `newSelect()`
  and friends is your responsibility.
- `object` columns (and the naming-convention fallback for objects) are deserialized with
  `zumba/json-serializer`, which recreates the class named in each `@type` key. Storm
  restricts this to an allow-list, which is **empty by default**. Generate the list with
  `vendor/bin/storm-allowlist` and keep it as small as you can: list only the value objects
  you store, never classes with side effects in `__wakeup()`/`__destruct()`.
  `SerializerProxy::unrestricted()` turns the protection off; don't use it for data anyone
  else can write.
- Storm requires `zumba/json-serializer` 3.2.3 or later. Earlier versions have no
  allow-list support and are affected by advisory PKSA-zhg7-9xn9-qv5j.
