extract-component-packages progress
---

This is working reasonable well but there are still many issues to be sorted out.

# Manual changes.

1. Customise lib/classes/component.php
2. Customise lib/xmldb/xmldb_structure.php
3. Customise cache/classes/helper.php (early_get_cache_plugins() needs to use component loader directly)
4. Customise admin/tool/phpunit/cli/init.php (needs to passthru to cli front controller)