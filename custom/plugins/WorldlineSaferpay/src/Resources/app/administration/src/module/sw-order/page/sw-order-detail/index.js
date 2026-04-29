import template from './sw-order-detail.html.twig';
import hasLastOrderTransactionHandledViaSaferpayRefunds from '../../component/sw-order-line-items-grid/index.js'
import getLastOrderTransactionHandledViaSaferpay from '../../component/sw-order-detail-details/index.js'

Shopware.Component.override('sw-order-detail', {
    template,

    computed: {
        orderCriteria() {
            const criteria = this.$super('orderCriteria')

            criteria.addAssociation('transactions.captures.refunds.positions')

            return criteria
        },

        wlsp__hasLastOrderTransactionHandledViaSaferpayRefunds() {
            return hasLastOrderTransactionHandledViaSaferpayRefunds(this.order)
        },

        wlsp__hasLastOrderTransactionHandledViaSaferpay() {
            return (getLastOrderTransactionHandledViaSaferpay(this.order))
        },
    },
});
