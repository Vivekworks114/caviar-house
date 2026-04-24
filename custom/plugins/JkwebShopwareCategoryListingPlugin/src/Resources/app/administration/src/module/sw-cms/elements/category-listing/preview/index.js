import template from './sw-cms-el-preview-category-listing.html.twig';
import './sw-cms-el-preview-category-listing.scss';
const { Filter } = Shopware;

Shopware.Component.register('sw-cms-el-preview-category-listing', {
    template,
    computed: {
        assetFilter() {
            return Filter.getByName('asset');
        },
    },
});
