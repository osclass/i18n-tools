# i18n tools

Generate .pot files of plugins and themes.

## Quick start

Cone the repo: ``git clone git@github.com:osclass/i18n-tools.git``

## All files for Osclass

```
php makepot.php all <Osclass' root folder>
```

This will generate all the files for Osclass and store them in "tmp/" folder

## Core and flash messages

```
php makepot.php core <Osclass' root folder> <pot file>
php makepot.php messages <Osclass' root folder> <pot file>
```

## E-mails (taken from mail.sql at oc-content/languages/xx_XX/)

```
php makepot.php mail <language folder> <pot file>
```

## Theme

```
php makepot.php plugin <plugin folder> <pot file>
```

## Plugin

```
php makepot.php theme <theme folder> <pot file>
```

## Credits

The base code of this tools are from [wordpress i18n tools](http://svn.automattic.com/wordpress-i18n/tools/trunk/).