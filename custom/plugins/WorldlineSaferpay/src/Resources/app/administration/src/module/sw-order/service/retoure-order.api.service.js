const ApiService = Shopware.Classes.ApiService;

/**
 * Gateway for the API endpoint "promotion codes"
 * @class
 * @extends ApiService
 */

export default class RetoureOrderApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = 'worldline-saferpay/refund') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'retoureOrderApiService';
    }

    /**
     * @param {Object} retoureData
     *
     * @returns {Promise<T>}
     */
    wlsp__sendRetoureData(retoureData) {
        const headers = this.getBasicHeaders();

        return this.httpClient.post(
            `/_action/${this.getApiBasePath()}/${retoureData.orderId}`,
            {
                retoureData,
            },
            {
                headers,
            },
        ).then((response) => {
            return ApiService.handleResponse(response);
        });
    }
}
