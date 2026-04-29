import './module/sw-order/component/sw-order-detail-details/';
import './module/sw-order/component/sw-order-line-items-grid/';
import './module/sw-order/view/sw-order-detail-general/';
import './module/sw-order/page/sw-order-detail/';

Shopware.Component.register('sw-order-detail-wlsprefunds', () => import('./module/sw-order/view/sw-order-detail-wlsprefunds'));

Shopware.Module.register('sw-order-detail-wlsprefunds-route', {
    routeMiddleware(next, currentRoute) {
        if (currentRoute.name === 'sw.order.detail') {
            currentRoute.children.push({
                name: 'sw.order.detail.wlsprefunds',
                component: 'sw-order-detail-wlsprefunds',
                path: 'wlsprefunds',
                meta: {
                    parentPath: 'sw.order.index',
                    privilege: 'order.viewer',
                },
            });
        }
        next(currentRoute);
    }
});
