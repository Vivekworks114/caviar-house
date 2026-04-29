import KlarnaPayments from './klarna-payments/klarna-payments';
import KlarnaExpressCheckout from './klarna-payments/klarna-express-checkout';
import SignInWithKlarna from './klarna-payments/sign-in-with-klarna.plugin';

const PluginManager = window.PluginManager;

PluginManager.register('KlarnaPayments', KlarnaPayments, '[data-is-klarna-payments]');
PluginManager.register('KlarnaExpressCheckout', KlarnaExpressCheckout, '[data-is-klarna-express-checkout]');
PluginManager.register('SignInWithKlarna', SignInWithKlarna, '[data-sign-in-with-klarna]');

if (module.hot) {
    module.hot.accept();
}
