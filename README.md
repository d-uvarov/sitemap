Sitemap
===================

Simple sitemap generator for php

## Installation

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

```
composer require d-uvarov/sitemap
```

or add to the `require` section of your `composer.json` file.

```
"d-uvarov/sitemap": "0.*"
```


## Quick start

```php
$sitemap = new Sitemap('http://example.com', __DIR__, 'sitemap.xml');

$sitemap->addUrl('/link1');
$sitemap->addUrl('/link2', time());
$sitemap->addUrl('/link3', time(), Sitemap::CHANGEFREQ_HOURLY);
$sitemap->addUrl('/link4', time(), Sitemap::CHANGEFREQ_DAILY, 0.3);
$sitemap->addUrl('/link5', time(), Sitemap::CHANGEFREQ_DAILY, 0.3);

$sitemap->write();
```