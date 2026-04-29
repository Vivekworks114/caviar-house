import template from './sw-order-detail-details.html.twig';

const getLastOrderTransactionHandledViaSaferpay = function(order) {
    if (!order || !order.transactions) {
        return null;
    }

    const transactions = Array.from(order.transactions);
    if (!transactions.length) {
        return null;
    }

    transactions.sort((a, b) => {
        return String(a.createdAt || "").localeCompare(String(b.createdAt || ""));
    });

    const lastTransaction = transactions.pop();
    if (!lastTransaction) {
        return null;
    }

    const paymentMethod = lastTransaction.paymentMethod;

    if (!paymentMethod || paymentMethod.handlerIdentifier !== 'Worldline\\Saferpay\\Checkout\\Payment\\Cart\\PaymentHandler\\SaferpayPaymentHandler') {
        return null;
    }

    return lastTransaction;
}

export default getLastOrderTransactionHandledViaSaferpay

Shopware.Component.override('sw-order-detail-details', {
    template,

    methods: {
        getLastOrderTransactionHandledViaSaferpay() {
            return getLastOrderTransactionHandledViaSaferpay(this.order);
        }
    }
});
