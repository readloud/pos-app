// src/services/api.js
import axios from 'axios';

const api = axios.create({
    baseURL: 'http://localhost:8000/api',
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
});

// Add token to requests
api.interceptors.request.use((config) => {
    const token = localStorage.getItem('token');
    if (token) {
        config.headers.Authorization = `Bearer ${token}`;
    }
    return config;
});

// Auth endpoints
export const login = (credentials) => api.post('/login', credentials);
export const logout = () => api.post('/logout');
export const getUser = () => api.get('/user');

// Product endpoints
export const getProducts = (params) => api.get('/products', { params });
export const searchProducts = (query) => api.get('/products/search', { params: { query } });
export const getProductByBarcode = (barcode) => api.get(`/products/barcode/${barcode}`);

// Cart endpoints
export const getCart = () => api.get('/cart');
export const addToCart = (data) => api.post('/cart/items', data);
export const updateCartItem = (itemId, quantity) => api.put(`/cart/items/${itemId}`, { quantity });
export const removeCartItem = (itemId) => api.delete(`/cart/items/${itemId}`);
export const clearCart = () => api.delete('/cart');

// Sale endpoints
export const createSale = (data) => api.post('/sales', data);
export const getSales = () => api.get('/sales');
export const getSaleInvoice = (id) => api.get(`/sales/${id}/invoice`);

export default api;
