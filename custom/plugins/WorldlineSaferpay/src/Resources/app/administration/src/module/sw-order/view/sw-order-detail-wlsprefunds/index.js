import template from './sw-order-detail-wlsprefunds.html.twig'
import getLastOrderTransactionHandledViaSaferpay from '../../component/sw-order-detail-details/index.js'

const { Store } = Shopware;

const getLastOrderTransactionHandledViaSaferpayRefunds = function(order) {
    const transaction = getLastOrderTransactionHandledViaSaferpay(order)

    if (!transaction) return []

    let refunds = []

    transaction.captures.forEach(capture => {
        if (capture.refunds && capture.refunds.length) refunds.push({id: capture.id, refunds: capture.refunds})
    })

    return refunds
}


export default {
    template,

    inject: {
        acl: {
            from: 'acl',
            default: null,
        },
    },

    props: {
        orderId: {
            type: String,
            required: true,
        },
    },

    computed: {
        loading: () => Store.get('swOrderDetail').loading,
        
        order: () => Store.get('swOrderDetail').order,

        currency() {
            return this.order.currency;
        },

        currencyFilter() {
            return Shopware.Filter.getByName('currency');
        },

        wlsp__lastOrderTransactionHandledViaSaferpayRefunds() {
            return getLastOrderTransactionHandledViaSaferpayRefunds(this.order)
        },
    },

    methods: {
        wlsp__getOrderLineItemById(orderLineItemId) {
            if (!this.order || !this.order.lineItems || !this.order.lineItems.length) return false

            const orderLineItem = this.order.lineItems.filter(orderLineItem => orderLineItem.id === orderLineItemId)

            return orderLineItem.length ? orderLineItem[0] : false
        },

        wlsp__formatRetourePositions(refund) {
            if (!refund || !refund.positions || !refund.positions.length) return []

            const formattedRetourePositions = []

            refund.positions.forEach(retourePosition => {
                const formattedRetourePosition = {}
                const orderLineItem = this.wlsp__getOrderLineItemById(retourePosition.orderLineItemId)

                formattedRetourePosition.label = orderLineItem ? orderLineItem.label : undefined
                formattedRetourePosition.quantity = retourePosition.quantity
                formattedRetourePosition.reason = retourePosition.reason
                formattedRetourePosition.unitPrice = retourePosition.amount.unitPrice
                formattedRetourePosition.totalPrice = retourePosition.amount.totalPrice

                formattedRetourePositions.push(formattedRetourePosition)
            });

            return formattedRetourePositions
        },

        wlsp__formatRetoureAdditionalPositions(refund) {
            if (
                !refund || !refund.customFields
                || !refund.customFields.worldline_saferpay_additional_positions
                || !Array.isArray(refund.customFields.worldline_saferpay_additional_positions)
                || !refund.customFields.worldline_saferpay_additional_positions.length
            ) {
                return []
            }

            const formattedRetourePositions = []

            refund.customFields.worldline_saferpay_additional_positions.forEach(retourePosition => {
                const formattedRetourePosition = {}

                formattedRetourePosition.label = retourePosition.label
                formattedRetourePosition.price = retourePosition.price

                formattedRetourePositions.push(formattedRetourePosition)
            });

            return formattedRetourePositions
        },

        wlsp__getRetoureDataItemColumns() {
            return [{
                property: 'quantity',
                label: 'sw-order.detailBase.columnQuantity',
            }, {
                property: 'label',
                label: 'sw-order.detailBase.columnProductName',
            }, {
                property: 'reason',
                label: 'saferpay.retoureDetail.column.reason',
            }, {
                property: 'unitPrice',
                label: 'saferpay.retoureDetail.column.unitPrice',
                align: 'right',
                width: '120px',
            }, {
                property: 'totalPrice',
                label: 'sw-order.detailBase.columnTotalPriceGross',
                align: 'right',
                width: '120px',
            }]
        },

        wlsp__getAdditionalRetoureDataItemColumns() {
            return [{
                property: 'label',
                label: 'saferpay.retoureBase.column.additionalRetoureItemLabel',
            }, {
                property: 'price',
                label: 'saferpay.retoureBase.column.additionalRetoureItemPrice',
            }]
        }
    }
}
