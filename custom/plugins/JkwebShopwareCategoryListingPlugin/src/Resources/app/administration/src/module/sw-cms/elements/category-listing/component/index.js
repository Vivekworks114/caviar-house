import template from './sw-cms-el-category-listing.html.twig';
import './sw-cms-el-category-listing.scss';

const { Criteria, EntityCollection } = Shopware.Data;
const { Filter } = Shopware;

Shopware.Component.register('sw-cms-el-category-listing', {
    template,
    inject: ['repositoryFactory'],
    mixins: [Shopware.Mixin.getByName('cms-element')],
    computed: {
        categoryRepository() {
            return this.repositoryFactory.create('category');
        },
        assetFilter() {
            return Filter.getByName('asset');
        },
    },
    data() {
        return {
            categoryCollection: null,
            context: Shopware.Context.api,
        };
    },
    created() {
        this.initElementConfig('category-listing');
        this.categoryCollection = new EntityCollection(
            '/category',
            'category',
            this.context
        );
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
                    this.updateCategoriesDataValue();
                });
        }
    },
    methods: {
        updateCategoriesDataValue() {
            if (this.element.config.categories.value) {
                const categories = [];
                this.categoryCollection.forEach((category) => {
                    categories.push(category);
                });
                this.$set(this.element.data, 'categories', categories);
            }
        },
    },
});
