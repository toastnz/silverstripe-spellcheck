# Spellcheck for SilverStripe

[![CI](https://github.com/silverstripe/silverstripe-spellcheck/actions/workflows/ci.yml/badge.svg)](https://github.com/silverstripe/silverstripe-spellcheck/actions/workflows/ci.yml)
[![Silverstripe supported module](https://img.shields.io/badge/silverstripe-supported-0071C4.svg)](https://www.silverstripe.org/software/addons/silverstripe-commercially-supported-module-list/)

Improves spellcheck support for Silverstripe CMS, including an implementation for HunSpell.

## Installation

Ensure that your server is setup with [hunspell](http://hunspell.sourceforge.net/), and the necessary
[dictionaries](http://download.services.openoffice.org/files/contrib/dictionaries/) for each language you wish to use.

Install the spellcheck module with composer, using `composer require silverstripe/spellcheck ^2.0`, or downloading
the module and extracting to the 'spellcheck' directory under your project root.

## Requirements

* Silverstripe 4.0.2 or above
* Hunspell

**Note:** this version is compatible with Silverstripe 4. For Silverstripe 3, please see [the 1.x release line](https://github.com/silverstripe/silverstripe-spellcheck/tree/1.0).

## Configuration

Setup the locales you wish to check for using yaml. If you do not specify any, it will default to the current
i18n default locale, and may not be appropriate if you have not configured dictionaries for some locales.

mysite/\_config/config.yml

```yaml
SilverStripe\SpellCheck\Handling\SpellController:
  locales:
    - en_NZ
    - fr_FR
    - de_DE
```

By default only users with the `CMS_ACCESS_CMSMain` permission may perform spellchecking. This permisson
code can be altered (or at your own risk, removed) by configuring the `SilverStripe\SpellCheck\Handling\SpellController.required_permission` config.

```yaml
SilverStripe\SpellCheck\Handling\SpellController:
  # Restrict to admin only
  required_permission: 'ADMIN'
```

## Extending

Additional spell check services can be added by implementing the `SilverStripe\SpellCheck\Providers\SpellProvider` interface and setting this as 
the default provider using yaml.

mysite/\_config/config.yml

```yaml
---
Name: myspellcheckprovider
After: '#spellcheckprovider'
---
# Set the default provider to HunSpell
SilverStripe\Core\Injector\Injector:
  SilverStripe\SpellCheck\Data\SpellProvider
    class: MySpellProvider
```
