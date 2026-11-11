import AsyncStorage from '@react-native-async-storage/async-storage';
import Constants from 'expo-constants';
import { clearCache, readCache, writeCache } from './cache';

// In dev the API lives on the same machine as the Expo dev server.
const devHost = Constants.expoConfig?.hostUri?.split(':')[0];
const DEFAULT_BASE =
  process.env.EXPO_PUBLIC_API_URL ??
  (devHost ? `http://${devHost}:8000/api` : 'http://localhost:8000/api');

// Mutable so the address can be changed on the sign-in screen: a tunnel URL
// changes, and rebuilding the APK just to follow it is not practical.
export let API_BASE = DEFAULT_BASE;
export let API_ORIGIN = originOf(DEFAULT_BASE);

function originOf(base: string) {
  return base.replace(/\/api\/?$/, '');
}

const SERVER_KEY = 'agrimall.server';

/** Turns "host", "https://host" or "https://host/api" into a /api base. */
export function normalizeApiBase(input: string): string | null {
  let raw = input.trim().replace(/\/+$/, '');
  if (!raw) return null;
  if (!/^https?:\/\//i.test(raw)) raw = `https://${raw}`;
  if (!/^https?:\/\/[^\s/]+(\/api)?$/i.test(raw)) return null;
  return raw.endsWith('/api') ? raw : `${raw}/api`;
}

export async function loadApiBase() {
  const saved = await AsyncStorage.getItem(SERVER_KEY).catch(() => null);
  if (saved) {
    API_BASE = saved;
    API_ORIGIN = originOf(saved);
  }
}

/** Returns the previous base so the caller can report where it was pointing. */
export async function saveApiBase(base: string): Promise<string> {
  const previous = API_ORIGIN;
  API_BASE = base;
  API_ORIGIN = originOf(base);
  await AsyncStorage.setItem(SERVER_KEY, base).catch(() => undefined);
  await clearCache();
  return previous;
}

export function imageUrl(path: string | null | undefined): string | undefined {
  if (!path) return undefined;
  return /^https?:/.test(path) ? path : `${API_ORIGIN}/${path.replace(/^\//, '')}`;
}

let token: string | null = null;
export function setToken(value: string | null) {
  token = value;
}

type Method = 'GET' | 'POST' | 'PUT' | 'DELETE';

/** The request never reached the server (offline, DNS, server down). */
export class NetworkError extends Error {}

async function request<T>(path: string, method: Method = 'GET', body?: unknown): Promise<T> {
  let res: Response;
  try {
    res = await fetch(API_BASE + path, {
      method,
      headers: {
        Accept: 'application/json',
        ...(body ? { 'Content-Type': 'application/json' } : {}),
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
      },
      body: body ? JSON.stringify(body) : undefined,
    });
  } catch {
    throw new NetworkError(`Cannot reach the server at ${API_ORIGIN}.`);
  }
  const data = await res.json().catch(() => null);
  if (!res.ok || !data?.ok) {
    const err = new Error(data?.error ?? `Request failed (${res.status}).`) as Error & { data?: unknown };
    err.data = data;
    throw err;
  }
  return data as T;
}

/** GET with an offline fallback: last good response, flagged as stale. */
async function requestCached<T>(path: string): Promise<T & { stale: boolean }> {
  try {
    const data = await request<T>(path);
    void writeCache(path, data);
    return { ...data, stale: false };
  } catch (error) {
    if (error instanceof NetworkError) {
      const cached = await readCache<T>(path);
      if (cached) return { ...cached, stale: true };
    }
    throw error;
  }
}

function query(params: Record<string, string | number | undefined>) {
  return Object.entries(params)
    .filter(([, v]) => v !== undefined && v !== '')
    .map(([k, v]) => `${encodeURIComponent(k)}=${encodeURIComponent(String(v))}`)
    .join('&');
}

export type Role = 'buyer' | 'farmer' | 'admin' | 'manager';
export type User = { id: number; email: string; role: Role };

export type Category = { id: number; name: string; slug: string };

export type Listing = {
  id: number;
  name: string;
  price: number;
  unit: string;
  category: string;
  category_slug: string;
  farm_name: string;
  province: string | null;
  image: string | null;
  available_qty: number;
  min_order: number;
  is_organic: boolean;
  is_expired: boolean;
  discount: number;
  save_count: number;
};

export type ListingDetail = {
  id: number;
  name: string;
  description: string | null;
  price: number;
  unit: string;
  category: string;
  available_qty: number;
  min_order: number;
  max_order: number | null;
  is_organic: boolean;
  harvest_date: string | null;
  expiry_date: string | null;
  is_expired: boolean;
  discount: number;
  images: string[];
  save_count: number;
  is_saved: boolean;
  sold_this_week: number;
  seller: {
    id: number;
    farm_name: string;
    barangay: string | null;
    municipality: string | null;
    province: string | null;
    verified: boolean;
  };
};

export type CartItem = {
  cart_id: number;
  listing_id: number;
  name: string;
  price_per_unit: number;
  quantity: number;
  minimum_order_qty: number;
  maximum_order_qty: number | null;
  available_quantity: number;
  farm_name: string;
  symbol: string;
  primary_image: string | null;
  subtotal: number;
};

export type OrderStatus =
  | 'pending'
  | 'delivery_fee_review'
  | 'confirmed'
  | 'preparing'
  | 'ready_for_pickup'
  | 'out_for_delivery'
  | 'completed'
  | 'rejected'
  | 'cancelled';

export type OrderSummary = {
  id: number;
  order_number: string;
  status: OrderStatus;
  created_at: string;
  listing_id: number;
  listing_name: string;
  quantity: number;
  unit_price: number;
  unit: string;
  total: number;
  delivery_method: string;
  delivery_fee: number;
  farm_name: string;
};

export type OrderDetail = OrderSummary & {
  delivery_address: string | null;
  preferred_pickup_date: string | null;
  preferred_pickup_time: string | null;
  buyer_note: string | null;
  farmer_note: string | null;
  buyer_name: string;
  buyer_id: number;
  farmer_id: number;
};

export type OrderMessage = {
  id: number;
  sender_id: number;
  sender_role: string;
  body: string;
  created_at: string;
  read_at: string | null;
};

export type SavedListing = {
  listing_id: number;
  name: string;
  price: number;
  unit: string;
  farm_name: string;
  image: string | null;
  price_at_save: number | null;
  price_dropped: boolean;
};

export type ProfileResponse = {
  user: User;
  profile: Record<string, string | null>;
  unread_notifications: number;
};

export type OrderAction =
  | 'confirm'
  | 'set_fee'
  | 'reject'
  | 'prepare'
  | 'ready'
  | 'ship'
  | 'complete'
  | 'cancel';

export type FarmerListing = {
  id: number;
  name: string;
  category: string;
  price: number;
  unit: string;
  available_qty: number;
  min_order: number;
  status: 'draft' | 'active' | 'paused' | 'sold_out' | 'archived' | 'admin_deactivated';
  is_organic: boolean;
  is_expired: boolean;
  image: string | null;
  rejection_reason: string | null;
  updated_at: string;
};

export type FarmerOverview = {
  farm_name: string;
  verification_status: 'pending' | 'approved' | 'rejected';
  stats: {
    pending_orders: number;
    active_orders: number;
    completed_orders: number;
    active_listings: number;
    sold_out_listings: number;
    revenue_this_month: number;
  };
  recent_orders: {
    id: number;
    order_number: string;
    status: OrderStatus;
    created_at: string;
    listing_name: string;
    quantity: number;
    unit: string;
    total: number;
    delivery_method: string;
  }[];
};

export type AdminApplication = {
  user_id: number;
  email: string;
  farm_name: string;
  contact_person: string;
  contact_phone: string | null;
  location: string;
  id_type: string;
  id_number: string;
  has_id_image: boolean;
  verification_status: 'pending' | 'approved' | 'rejected';
  rejection_reason: string | null;
  registered_at: string;
};

export type AdminUser = {
  id: number;
  email: string;
  role: Role;
  status: 'active' | 'suspended' | 'pending_verification' | 'anonymized';
  name: string | null;
  verification_status: 'pending' | 'approved' | 'rejected' | null;
  created_at: string;
  last_login_at: string | null;
};

export const api = {
  login: (email: string, password: string, totp_code?: string) =>
    request<{ token: string; user: User }>('/auth/login.php', 'POST', {
      email,
      password,
      ...(totp_code ? { totp_code } : {}),
      device_name: 'AgriMALL Mobile',
    }),

  register: (payload: { full_name: string; email: string; password: string; role: Role }) =>
    request<{ message: string; email: string; dev_code: string | null }>(
      '/auth/register.php',
      'POST',
      payload,
    ),

  verifyOtp: (payload: {
    email: string;
    code: string;
    full_name: string;
    password: string;
    role: Role;
  }) => request<{ token: string; user: User }>('/auth/verify-otp.php', 'POST', payload),

  categories: () => request<{ categories: Category[] }>('/categories.php'),

  listings: (params: { q?: string; category?: string; sort?: string; page?: number }) =>
    requestCached<{ total: number; page: number; total_pages: number; listings: Listing[] }>(
      `/listings.php?${query(params)}`,
    ),

  listing: (id: number) =>
    requestCached<{ listing: ListingDetail; related: Listing[] }>(`/listings/detail.php?id=${id}`),

  cart: () =>
    request<{ items: CartItem[]; total: number; count: number }>('/cart/index.php'),

  addToCart: (listing_id: number, quantity: number) =>
    request<{ cart_count: number; message: string }>('/cart/add.php', 'POST', {
      listing_id,
      quantity,
    }),

  updateCart: (item_id: number, quantity: number) =>
    request<{ message: string }>('/cart/update.php', 'POST', { item_id, quantity }),

  removeCart: (item_id: number) =>
    request<{ message: string }>('/cart/remove.php', 'POST', { item_id }),

  checkout: (payload: {
    delivery_method: 'pickup' | 'farmer_delivery';
    delivery_address?: string;
    buyer_note?: string;
  }) => request<{ order_id: number; message: string }>('/cart/checkout.php', 'POST', payload),

  orders: (params: { status?: string; page?: number }) =>
    requestCached<{ total: number; page: number; total_pages: number; orders: OrderSummary[] }>(
      `/orders.php?${query(params)}`,
    ),

  order: (id: number) =>
    requestCached<{
      order: OrderDetail;
      history: { status: OrderStatus; created_at: string; note: string | null }[];
      messages: OrderMessage[];
    }>(`/orders/detail.php?id=${id}`),

  orderAction: (
    order_id: number,
    action: OrderAction,
    extra: { delivery_fee?: number; reason?: string } = {},
  ) =>
    request<{ status: OrderStatus; message: string }>('/orders/action.php', 'POST', {
      order_id,
      action,
      ...extra,
    }),

  sendMessage: (order_id: number, body: string) =>
    request<{ message: string }>('/orders/message.php', 'POST', { order_id, body }),

  saved: () => requestCached<{ saved: SavedListing[]; count: number }>('/saved/index.php'),

  toggleSaved: (listing_id: number) =>
    request<{ saved: boolean; message: string }>('/saved/toggle.php', 'POST', { listing_id }),

  profile: () => requestCached<ProfileResponse>('/profile/index.php'),

  updateProfile: (payload: Record<string, string>) =>
    request<{ message: string }>('/profile/index.php', 'PUT', payload),

  farmerOverview: () => requestCached<FarmerOverview>('/farmer/overview.php'),

  farmerListings: () =>
    requestCached<{ listings: FarmerListing[]; count: number }>('/farmer/listings.php'),

  updateFarmerListing: (payload: {
    id: number;
    price?: number;
    quantity?: number;
    status?: 'active' | 'paused';
  }) =>
    request<{
      listing: { id: number; price: number; available_qty: number; status: FarmerListing['status'] };
      message: string;
    }>('/farmer/listing.php', 'POST', payload),

  adminApplications: (status: 'pending' | 'approved' | 'rejected' | 'all' = 'pending') =>
    requestCached<{ applications: AdminApplication[]; pending_total: number }>(
      `/admin/verify.php?status=${status}`,
    ),

  adminReview: (farmer_id: number, action: 'approve' | 'reject', reason?: string) =>
    request<{ message: string }>('/admin/verify.php', 'POST', { farmer_id, action, reason }),

  adminUsers: (params: { q?: string; role?: string; status?: string; page?: number }) =>
    requestCached<{ total: number; page: number; total_pages: number; users: AdminUser[] }>(
      `/admin/users.php?${query(params)}`,
    ),

  registerPushToken: (token: string, platform: 'ios' | 'android', device_name?: string) =>
    request<{ message: string }>('/push/register.php', 'POST', { token, platform, device_name }),

  unregisterPushToken: (token: string) =>
    request<{ message: string }>('/push/register.php', 'DELETE', { token }),
};
