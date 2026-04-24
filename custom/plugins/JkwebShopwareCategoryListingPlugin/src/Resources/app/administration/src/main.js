import deDE from './module/sw-cms/snippet/de-DE.json';
import enGB from './module/sw-cms/snippet/en-GB.json';

// Blocks
import './module/sw-cms/blocks/commerce/category-listing';

// Elements
import './module/sw-cms/elements/category-listing';

Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);
