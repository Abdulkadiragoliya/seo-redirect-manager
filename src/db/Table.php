<?php

namespace abdulkadir\seoredirectmanager\db;

abstract class Table
{
    public const REDIRECTS = '{{%seoredirect_redirects}}';
    public const NOT_FOUND = '{{%seoredirect_not_found}}';
    public const SHORT_LINKS = '{{%seoredirect_short_links}}';
    public const AUDIT = '{{%seoredirect_audit}}';
}
