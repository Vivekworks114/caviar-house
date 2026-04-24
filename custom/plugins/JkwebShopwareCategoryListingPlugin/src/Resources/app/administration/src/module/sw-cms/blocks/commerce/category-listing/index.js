import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'category-listing',
    label: 'sw-cms.blocks.commerce.categoryListing.label',
    category: 'commerce',
    component: 'sw-cms-block-category-listing',
    previewComponent: 'sw-cms-preview-category-listing',
    defaultConfig: {
        marginBottom: '0',
        marginTop: '0',
        marginLeft: '0',
        marginRight: '0',
        sizingMode: 'boxed',
    },
    slots: {
        content: 'category-listing',
    },
});
