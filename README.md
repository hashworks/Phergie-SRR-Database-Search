# PhergieSRRDatabaseSearch

[Phergie](http://github.com/phergie/phergie-irc-bot-react/) plugin to search srrdb.com.

## Install

To install via [Composer](http://getcomposer.org/), use the command below, it will automatically detect the latest version and bind it with `~`.

```
composer require hashworks/phergie-plugin-srr-database-search
```

See Phergie documentation for more information on
[installing and enabling plugins](https://github.com/phergie/phergie-irc-bot-react/wiki/Usage#plugins).

## Configuration

```php
// dependency
new \Phergie\Irc\Plugin\React\Command\Plugin,
new \hashworks\Phergie\Plugin\SRRDatabaseSearch\Plugin(array(
    'limit' => 5, // Optional. Limit the number of results replied to the user.
    'hideUrlOnMultipleResults' => false // Optional. Hide URLs on multiple results.
)),
```

## Syntax

`srrdb <imdbid|archive-crc|dirname|query>`
