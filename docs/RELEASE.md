# Release & Publishing Guide

Instructions for creating a release tag and publishing to the Craft Plugin Store / Packagist.

> **Note**: These commands are to be executed manually in your terminal or Command Prompt.

## 1. Verify Clean Workspace and Tests

```cmd
cd c:\wamp64\www\craftLearning\plugins\seo-redirect-manager
php tests/run.php
```

Ensure all tests report `[PASS]`.

## 2. Initialize or Update Git Repository

```cmd
cd c:\wamp64\www\craftLearning\plugins\seo-redirect-manager
git init
git add .
git commit -m "feat: Initial production release of SEO Redirect Manager for Craft CMS 5"
```

## 3. Tag Release

```cmd
git tag -a 1.0.0 -m "Release 1.0.0 - Production enterprise redirect manager for Craft CMS 5"
```

## 4. Push to Remote & Packagist

```cmd
git remote add origin https://github.com/abdulkadir/seo-redirect-manager.git
git branch -M main
git push -u origin main --tags
```

Once pushed, submit the repository to [Packagist](https://packagist.org) and register it in the [Craft Plugin Store Developer Portal](https://plugins.craftcms.com).
