import template from './sw-cms-el-config-category-listing.html.twig';
import './sw-cms-el-config-category-listing.scss';

const { Criteria, EntityCollection } = Shopware.Data;

Shopware.Component.register('sw-cms-el-config-category-listing', {
    template,
    inject: ['repositoryFactory'],
    mixins: [Shopware.Mixin.getByName('cms-element')],
    computed: {
        categoryRepository() {
            return this.repositoryFactory.create('category');
        },
    },
    data() {
        return {
            categoryCollection: [],
            context: Shopware.Context.api,
            headingPositionValues: [
                {
                    label: this.$tc(
                        'sw-cms.elements.categoryListing.config.headingPosition.top'
                    ),
                    value: 'top',
                },
                {
                    label: this.$tc(
                        'sw-cms.elements.categoryListing.config.headingPosition.bottom'
                    ),
                    value: 'bottom',
                },
                {
                    label: this.$tc(
                        'sw-cms.elements.categoryListing.config.headingPosition.overlay'
                    ),
                    value: 'overlay',
                },
            ],
        };
    },
    created() {
        this.initElementConfig('category-listing');
        this.categoryCollection = new EntityCollection(
            '/category',
            'category',
            this.context
        );
        this.reloadCategoryCollection();
    },
    methods: {
        onCategoryChange(entityCollection) {
            this.element.config.categories.value = entityCollection.getIds();
            this.updateCategoriesDataValue();
        },
        reloadCategoryCollection(resolveCallback) {
            if (this.element.config.categories.value.length > 0) {
                const criteria = new Criteria(1, 100);
                criteria.addAssociation('media');
                criteria.setIds(this.element.config.categories.value);
                this.categoryRepository
                    .search(
                        criteria,
                        Object.assign({}, this.context, {
                            inheritance: true,
                        })
                    )
                    .then((result) => {
                        this.categoryCollection = result;
                        if (resolveCallback) {
                            resolveCallback();
                        }
                    });
            }
        },
        updateCategoriesDataValue() {
            if (this.element.config.categories.value) {
                this.reloadCategoryCollection(() => {
                    const categories = [];
                    this.categoryCollection.forEach((category) => {
                        categories.push(category);
                        this.selectedCategoryIds = category.id;
                    });
                    this.$set(this.element.data, 'categories', categories);
                });
            } else {
                this.$set(this.element.data, 'categories', []);
            }
        },
    },
});
