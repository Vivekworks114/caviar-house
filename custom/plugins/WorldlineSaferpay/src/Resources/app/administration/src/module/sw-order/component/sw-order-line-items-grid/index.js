import template from './sw-order-line-items-grid.html.twig'
import './sw-order-line-items-grid.scss'
import RetoureOrderApiService from '../../service/retoure-order.api.service'
import getLastOrderTransactionHandledViaSaferpay from '../sw-order-detail-details/index.js'

Shopware.Service().register('wlsp__retoureOrderApiService', () => {
    return new RetoureOrderApiService(
        Shopware.Application.getContainer('init').httpClient,
        Shopware.Service('loginService'),
    )
})

const { mapState } = Shopware.Component.getComponentHelper()
const WLSP__PAID_TECHNICAL_NAME = 'paid'
const { Mixin } = Shopware;
const hasLastOrderTransactionHandledViaSaferpayRefunds = function(order) {
    const transaction = getLastOrderTransactionHandledViaSaferpay(order)

    if (!transaction) return false

    let hasRefunds = false

    transaction.captures.forEach(capture => {
        if (capture.refunds.length) hasRefunds = true
    });

    return hasRefunds
}

export default hasLastOrderTransactionHandledViaSaferpayRefunds

const getRandomNumber = function(min, max) {
    return Math.floor(Math.random() * (max - min + 1) + min);
}

Shopware.Component.override('sw-order-line-items-grid', {
    template,

    inject: ['wlsp__retoureOrderApiService'],

    mixins: [
        Mixin.getByName('notification'),
    ],

    data() {
        return {
            selectedItems: {},
            wlsp__minRetoureQuantity: 1,
            wlsp__internalRetoureItems: [],
            wlsp__internalAdditionalRetoureItems: [],
            wlsp__retoureItems: [],
            wlsp__additionalRetoureItems: [],
            wlsp__isRetoureModalVisible: false,
            wlsp__refund: {
                orderId: null,
                positions: []
            },
            wlsp__pendingRequest: false
        }
    },
    computed: {
        wlsp__hasTransactionCaptures() {
            return getLastOrderTransactionHandledViaSaferpay(this.order)
                && getLastOrderTransactionHandledViaSaferpay(this.order).captures.length
        },
        wlsp__hasLastOrderTransactionHandledViaSaferpay() {
            return (getLastOrderTransactionHandledViaSaferpay(this.order))
        },
        wlsp__isLastOrderTransactionHandledViaSaferpayPaid() {
            return getLastOrderTransactionHandledViaSaferpay(this.order).stateMachineState.technicalName === WLSP__PAID_TECHNICAL_NAME
        },
        wlsp__hasLastOrderTransactionHandledViaSaferpayRefunds() {
            return hasLastOrderTransactionHandledViaSaferpayRefunds(this.order)
        },
        wlsp__retoureItemsSum() {
            let retoureItemsSum = 0

            this.wlsp__refund.positions && this.wlsp__refund.positions.forEach(retoureItem => {
                retoureItemsSum += retoureItem.unitPrice * retoureItem.quantity
            })

            this.wlsp__refund.additionalPositions && this.wlsp__refund.additionalPositions.forEach(additionalRetoureItem => {
                retoureItemsSum += additionalRetoureItem.price
            })

            return retoureItemsSum
        },
        ...mapState('swOrderDetail', [
            'order',
        ]),
        currency() {
            return this.order.currency
        },

        currencyFilter() {
            return Shopware.Filter.getByName('currency');
        },
    },
    methods: {
        wlsp__showRetoureModal() {
            this.wlsp__setupRetoureItemData()
            this.wlsp__isRetoureModalVisible = true
        },

        wlsp__closeRetoureModal() {
            this.wlsp__resetRetoureData()
            this.wlsp__isRetoureModalVisible = false
        },

        wlsp__getRetoureLineItemColumns() {
            const columnDefinitions = [{
                property: 'label',
                dataIndex: 'label',
                label: 'sw-order.detailBase.columnProductName',
                allowResize: false,
                primary: true,
                inlineEdit: false,
                multiLine: true,
            }, {
                property: 'quantity',
                dataIndex: 'quantity',
                label: 'sw-order.detailBase.columnQuantity',
                allowResize: false,
                inlineEdit: false,
            }, {
                property: 'wlsp__retoureQuantity',
                dataIndex: 'wlsp__retoureQuantity',
                label: 'saferpay.retoureBase.column.retoureQuantity',
                allowResize: false,
                inlineEdit: 'number',
            }, {
                property: 'wlsp__retoureComment',
                dataIndex: 'wlsp__retoureComment',
                label: 'saferpay.retoureBase.column.retoureComment',
                allowResize: false,
                inlineEdit: 'string',
                multiLine: true,
            }]

            return columnDefinitions
        },

        wlsp__onRetoureInlineEditCancel() {
            this.wlsp__resetAllItemsRetoureData()
        },

        wlsp__onRetoureInlineEditSave(item) {
            this.wlsp__saveAllRetoureItemData(item)
            this.wlsp__setRetoureData()
        },

        wlsp__setupRetoureItemData() {
            const selectedItemIDs = Object.keys(this.selectedItems)

            if (!this.order.lineItems.length || !selectedItemIDs.length) {
                this.selectedItems = {}
                this.wlsp__retoureItems = []
                this.$refs.dataGrid.resetSelection()
                return
            }

            this.wlsp__retoureItems = this.order.lineItems.filter(lineItem => selectedItemIDs.indexOf(lineItem.id) > -1)

            // Set default values
            this.wlsp__retoureItems.forEach(item => {
                item.wlsp__retoureQuantity = this.wlsp__minRetoureQuantity
                item.wlsp__retoureComment = ''
            })

            // Make non-reactive copy of retoure items
            this.wlsp__saveAllRetoureItemData()

            // Set initial retoure data
            this.wlsp__setRetoureData()
        },

        wlsp__saveAllRetoureItemData() {
            this.wlsp__internalRetoureItems = JSON.parse(JSON.stringify(this.wlsp__retoureItems))
        },

        wlsp__resetAllItemsRetoureData() {
            this.wlsp__retoureItems.forEach(item => {
                const internalRetoureItem = this.wlsp__internalRetoureItems.filter(internalRetoureItem => internalRetoureItem.id === item.id).length ? this.wlsp__internalRetoureItems.filter(internalRetoureItem => internalRetoureItem.id === item.id)[0] : false

                if (!internalRetoureItem) {
                    console.warn && console.warn(`Can't find internal item with id ${item.id} to reset data from.`)
                }

                item.wlsp__retoureQuantity = internalRetoureItem.wlsp__retoureQuantity
                item.wlsp__retoureComment = internalRetoureItem.wlsp__retoureComment
            })
        },

        wlsp__resetRetoureData() {
            this.wlsp__refund = {
                orderId: this.order.id,
                positions: []
            };

            this.wlsp__retoureItems = []
            this.wlsp__internalRetoureItems = []
        },

        wlsp__setRetoureData() {
            this.wlsp__refund.orderId = this.order.id;
            this.wlsp__refund.positions = [];

            for (const item of this.wlsp__retoureItems) {
                this.wlsp__refund.positions.push({
                    quantity: item.wlsp__retoureQuantity,
                    reason: item.wlsp__retoureComment,
                    orderLineItemId: item.id,
                    unitPrice: item.unitPrice
                });
            }

            this.wlsp__refund.additionalPositions = [];

            for (const item of this.wlsp__additionalRetoureItems) {
                this.wlsp__refund.additionalPositions.push({
                    label: item.wlsp__additionalRetoureItemLabel,
                    price: item.wlsp__additionalRetoureItemPrice
                });
            }
        },

        wlsp__sendRetoureData() {
            if (this.wlsp__pendingRequest) return
            this.wlsp__pendingRequest = true

            this.wlsp__retoureOrderApiService.wlsp__sendRetoureData(this.wlsp__refund).then(response => {
                this.$emit('item-retoure')
            }).catch(error => {
                if (!error.response || !error.response.data || !error.response.data.errors.length) return Promise.reject(error)

                error.response.data.errors.forEach(errorResponse => {
                    this.createNotificationError({
                        title: errorResponse.title,
                        message: errorResponse.detail
                    })
                })

                return Promise.reject(error)
            }).finally(() => {
                this.wlsp__closeRetoureModal()
                this.wlsp__pendingRequest = false
            })
        },

        wlsp__addAdditionalRetoureItems() {
            this.wlsp__additionalRetoureItems.push({id: getRandomNumber(1,9999999), wlsp__additionalRetoureItemLabel: '', wlsp__additionalRetoureItemPrice: 0})
            this.wlsp__internalAdditionalRetoureItems = JSON.parse(JSON.stringify(this.wlsp__additionalRetoureItems))
        },

        wlsp__getAdditionalRetoureLineItemColumns() {
            const columnDefinitions = [{
                property: 'id',
                dataIndex: 'id',
                label: 'saferpay.retoureBase.column.additionalRetoureItemId',
                primary: true,
            }, {
                property: 'wlsp__additionalRetoureItemLabel',
                dataIndex: 'wlsp__additionalRetoureItemLabel',
                label: 'saferpay.retoureBase.column.additionalRetoureItemLabel',
                allowResize: false,
                inlineEdit: 'string',
                multiLine: true,
            },  {
                property: 'wlsp__additionalRetoureItemPrice',
                dataIndex: 'wlsp__additionalRetoureItemPrice',
                label: 'saferpay.retoureBase.column.additionalRetoureItemPrice',
                allowResize: false,
                inlineEdit: 'number'
            }]

            return columnDefinitions
        },

        wlsp__onAdditionalRetoureInlineEditCancel() {
            this.wlsp__resetAllAdditonalItemsRetoureData()
        },

        wlsp__onAdditionalRetoureInlineEditSave(item) {
            this.wlsp__saveAllAdditionalRetoureItemData(item)
            this.wlsp__setRetoureData()
        },

        wlsp__saveAllAdditionalRetoureItemData(item) {
            this.wlsp__internalAdditionalRetoureItems = JSON.parse(JSON.stringify(this.wlsp__additionalRetoureItems))
        },

        wlsp__resetAllAdditonalItemsRetoureData() {
            this.wlsp__additionalRetoureItems.forEach(item => {
                const internalAdditionalRetoureItem = this.wlsp__internalAdditionalRetoureItems.filter(internalAdditionalRetoureItem => internalAdditionalRetoureItem.id === item.id).length ? this.wlsp__internalAdditionalRetoureItems.filter(internalAdditionalRetoureItem => internalAdditionalRetoureItem.id === item.id)[0] : false

                if (!internalAdditionalRetoureItem) {
                    console.warn && console.warn(`Can't find internal additional item with id ${item.id} to reset data from.`)
                }

                item.wlsp__additionalRetoureItemLabel = internalAdditionalRetoureItem.wlsp__additionalRetoureItemLabel
                item.wlsp__additionalRetoureItemPrice = internalAdditionalRetoureItem.wlsp__additionalRetoureItemPrice
            })
        },

        wlsp__onDeleteAdditionalItem(item, itemIndex) {
            this.wlsp__additionalRetoureItems.splice(itemIndex, 1)

            // Update non-reactive copy of internal additional retoure items
            this.wlsp__internalAdditionalRetoureItems = JSON.parse(JSON.stringify(this.wlsp__additionalRetoureItems))

            this.wlsp__setRetoureData()
        },
    }
})
