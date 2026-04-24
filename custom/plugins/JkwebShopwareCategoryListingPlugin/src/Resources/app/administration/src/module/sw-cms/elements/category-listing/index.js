import './component';
import './preview';
import './config';

Shopware.Service('cmsService').registerCmsElement({
    name: 'category-listing',
    label: 'sw-cms.elements.categoryListing.label',
    component: 'sw-cms-el-category-listing',
    configComponent: 'sw-cms-el-config-category-listing',
    previewComponent: 'sw-cms-el-preview-category-listing',
    defaultConfig: {
        categories: {
            source: 'mapped',
            value: [],
        },
        rowElementClassName: {
            source: 'static',
            value: 'row',
        },
        colElementClassName: {
            source: 'static',
            value: 'col-md-6 col-lg-4',
        },
        headingPosition: {
            source: 'static',
            value: 'top',
        },
    },
});
