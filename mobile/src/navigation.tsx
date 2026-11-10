import { Ionicons } from '@expo/vector-icons';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { createNavigationContainerRef, type NavigatorScreenParams } from '@react-navigation/native';
import {
  createNativeStackNavigator,
  type NativeStackNavigationProp,
} from '@react-navigation/native-stack';
import type { Role } from './api';
import { useTheme } from './theme';
import { AdminUsersScreen } from './screens/AdminUsersScreen';
import { AdminVerifyScreen } from './screens/AdminVerifyScreen';
import { BrowseScreen } from './screens/BrowseScreen';
import { CartScreen } from './screens/CartScreen';
import { CheckoutScreen } from './screens/CheckoutScreen';
import { FarmerDashboardScreen } from './screens/FarmerDashboardScreen';
import { FarmerListingsScreen } from './screens/FarmerListingsScreen';
import { ListingScreen } from './screens/ListingScreen';
import { OrderDetailScreen } from './screens/OrderDetailScreen';
import { OrdersScreen } from './screens/OrdersScreen';
import { ProfileScreen } from './screens/ProfileScreen';
import { SavedScreen } from './screens/SavedScreen';

export type TabParams = {
  Browse: undefined;
  Cart: undefined;
  Orders: undefined;
  Dashboard: undefined;
  Listings: undefined;
  Verify: undefined;
  Users: undefined;
  Profile: undefined;
};

export type RootStackParams = {
  Tabs: NavigatorScreenParams<TabParams> | undefined;
  Listing: { id: number };
  Checkout: undefined;
  OrderDetail: { id: number };
  Saved: undefined;
};

export type RootNav = NativeStackNavigationProp<RootStackParams>;

const Stack = createNativeStackNavigator<RootStackParams>();
const Tab = createBottomTabNavigator<TabParams>();

export const navigationRef = createNavigationContainerRef<RootStackParams>();

const tabIcons: Record<keyof TabParams, keyof typeof Ionicons.glyphMap> = {
  Browse: 'storefront-outline',
  Cart: 'cart-outline',
  Orders: 'receipt-outline',
  Dashboard: 'stats-chart-outline',
  Listings: 'pricetags-outline',
  Verify: 'shield-checkmark-outline',
  Users: 'people-outline',
  Profile: 'person-outline',
};

/** Tabs each role can reach. Admin/manager keep the buyer tabs so they can
 *  still place an order from the phone. */
function tabsFor(role: Role): (keyof TabParams)[] {
  if (role === 'farmer') return ['Dashboard', 'Orders', 'Listings', 'Profile'];
  if (role === 'admin' || role === 'manager') {
    return ['Browse', 'Cart', 'Verify', 'Users', 'Profile'];
  }
  return ['Browse', 'Cart', 'Orders', 'Profile'];
}

function Tabs({ role }: { role: Role }) {
  const t = useTheme();
  const tabs = tabsFor(role);

  return (
    <Tab.Navigator
      screenOptions={({ route }) => ({
        headerStyle: { backgroundColor: t.canvas },
        headerShadowVisible: false,
        headerTintColor: t.text,
        headerTitleStyle: { color: t.text, fontWeight: '700' },
        tabBarActiveTintColor: t.primary,
        tabBarInactiveTintColor: t.muted,
        tabBarStyle: { backgroundColor: t.canvas, borderTopColor: t.border },
        tabBarIcon: ({ color, size }) => (
          <Ionicons name={tabIcons[route.name as keyof TabParams]} size={size} color={color} />
        ),
      })}
    >
      {tabs.includes('Browse') ? (
        <Tab.Screen name="Browse" component={BrowseScreen} options={{ title: 'AgriMALL' }} />
      ) : null}
      {tabs.includes('Cart') ? <Tab.Screen name="Cart" component={CartScreen} /> : null}
      {tabs.includes('Orders') ? <Tab.Screen name="Orders" component={OrdersScreen} /> : null}
      {tabs.includes('Dashboard') ? (
        <Tab.Screen
          name="Dashboard"
          component={FarmerDashboardScreen}
          options={{ title: 'Dashboard' }}
        />
      ) : null}
      {tabs.includes('Listings') ? (
        <Tab.Screen
          name="Listings"
          component={FarmerListingsScreen}
          options={{ title: 'My produce' }}
        />
      ) : null}
      {tabs.includes('Verify') ? (
        <Tab.Screen
          name="Verify"
          component={AdminVerifyScreen}
          options={{ title: 'Verifications' }}
        />
      ) : null}
      {tabs.includes('Users') ? (
        <Tab.Screen name="Users" component={AdminUsersScreen} options={{ title: 'Accounts' }} />
      ) : null}
      {tabs.includes('Profile') ? <Tab.Screen name="Profile" component={ProfileScreen} /> : null}
    </Tab.Navigator>
  );
}

export function AppNavigator({ role }: { role: Role }) {
  const t = useTheme();
  return (
    <Stack.Navigator
      screenOptions={{
        headerStyle: { backgroundColor: t.canvas },
        headerShadowVisible: false,
        headerTintColor: t.primary,
        headerTitleStyle: { color: t.text, fontWeight: '700' },
        contentStyle: { backgroundColor: t.canvas },
      }}
    >
      <Stack.Screen name="Tabs" options={{ headerShown: false }}>
        {() => <Tabs role={role} />}
      </Stack.Screen>
      <Stack.Screen name="Listing" component={ListingScreen} options={{ title: 'Listing' }} />
      <Stack.Screen name="Checkout" component={CheckoutScreen} options={{ title: 'Checkout' }} />
      <Stack.Screen name="OrderDetail" component={OrderDetailScreen} options={{ title: 'Order' }} />
      <Stack.Screen name="Saved" component={SavedScreen} options={{ title: 'Saved listings' }} />
    </Stack.Navigator>
  );
}

/** Deep link config for agrimall:// and https://agrimall.ph. */
export const linking = {
  prefixes: ['agrimall://', 'https://agrimall.ph'],
  config: {
    screens: {
      Tabs: {
        screens: {
          Browse: 'browse',
          Cart: 'cart',
          Orders: 'orders',
          Dashboard: 'dashboard',
          Listings: 'listings',
          Verify: 'verify',
          Users: 'users',
          Profile: 'profile',
        },
      },
      Listing: 'listing/:id',
      Checkout: 'checkout',
      OrderDetail: 'order/:id',
      Saved: 'saved',
    },
  },
};

/** Notification taps carry a web-style link ("order.php?id=12"). */
export function openFromLink(link: string | null | undefined) {
  if (!link || !navigationRef.isReady()) return;
  const order = link.match(/order(?:\.php\?id=|\/)(\d+)/);
  if (order) {
    navigationRef.navigate('OrderDetail', { id: Number(order[1]) });
    return;
  }
  const listing = link.match(/listing(?:\.php\?id=|\/)(\d+)/);
  if (listing) {
    navigationRef.navigate('Listing', { id: Number(listing[1]) });
    return;
  }
  if (link.includes('orders')) navigationRef.navigate('Tabs', { screen: 'Orders' });
}
