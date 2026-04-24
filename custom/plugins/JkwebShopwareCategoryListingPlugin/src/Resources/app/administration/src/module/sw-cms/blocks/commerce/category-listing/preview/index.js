import template from './sw-cms-preview-category-listing.html.twig';
import './sw-cms-preview-category-listing.scss';
const { Filter } = Shopware;

Shopware.Component.register('sw-cms-preview-category-listing', {
    template,
    computed: {
        assetFilter() {
            return Filter.getByName('asset');
        },
    },
});
