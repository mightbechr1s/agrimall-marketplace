import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect, useNavigation } from '@react-navigation/native';
import { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Image,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { api, imageUrl, type CartItem } from '../api';
import type { RootNav } from '../navigation';
import { money, useTheme } from '../theme';
import { Button, Empty, ErrorNote, Stepper } from '../ui';

export function CartScreen() {
  const t = useTheme();
  const navigation = useNavigation<RootNav>();
  const [items, setItems] = useState<CartItem[]>([]);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    try {
      const res = await api.cart();
      setItems(res.items);
      setTotal(res.total);
      setError('');
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not load your cart.');
    } finally {
      setLoading(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  const setQuantity = async (item: CartItem, quantity: number) => {
    setItems((prev) =>
      prev.map((i) =>
        i.cart_id === item.cart_id
          ? { ...i, quantity, subtotal: quantity * i.price_per_unit }
          : i,
      ),
    );
    setTotal((prev) => prev + (quantity - item.quantity) * item.price_per_unit);
    try {
      await api.updateCart(item.cart_id, quantity);
    } catch (e) {
      Alert.alert('Could not update quantity', e instanceof Error ? e.message : 'Try again.');
      load();
    }
  };

  const remove = (item: CartItem) => {
    Alert.alert('Remove item?', item.name, [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Remove',
        style: 'destructive',
        onPress: async () => {
          try {
            await api.removeCart(item.cart_id);
            load();
          } catch (e) {
            Alert.alert('Could not remove item', e instanceof Error ? e.message : 'Try again.');
          }
        },
      },
    ]);
  };

  if (loading) {
    return (
      <View style={[styles.fill, { backgroundColor: t.canvas }]}>
        <ActivityIndicator color={t.primary} />
      </View>
    );
  }

  if (error) {
    return (
      <View style={[styles.fill, { backgroundColor: t.canvas }]}>
        <ErrorNote message={error} onRetry={load} />
      </View>
    );
  }

  if (items.length === 0) {
    return (
      <View style={{ flex: 1, backgroundColor: t.canvas }}>
        <Empty
          icon="cart-outline"
          title="Your cart is empty"
          hint="Browse the market and add produce from verified farmers."
          action={
            <Button
              label="Browse produce"
              onPress={() => navigation.navigate('Tabs', { screen: 'Browse' })}
            />
          }
        />
      </View>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: t.canvas }}>
      <FlatList
        data={items}
        keyExtractor={(item) => String(item.cart_id)}
        contentContainerStyle={{ padding: 16 }}
        renderItem={({ item }) => (
          <View style={[styles.row, { borderColor: t.border }]}>
            {imageUrl(item.primary_image) ? (
              <Image source={{ uri: imageUrl(item.primary_image) }} style={styles.thumb} />
            ) : (
              <View style={[styles.thumb, styles.thumbFallback, { backgroundColor: t.surface }]}>
                <Ionicons name="leaf-outline" size={22} color={t.muted} />
              </View>
            )}
            <View style={{ flex: 1 }}>
              <Text numberOfLines={2} style={{ color: t.text, fontWeight: '600' }}>
                {item.name}
              </Text>
              <Text style={{ color: t.muted, fontSize: 12, marginTop: 2 }}>{item.farm_name}</Text>
              <Text style={{ color: t.text, marginTop: 4 }}>
                {money(item.price_per_unit)} / {item.symbol}
              </Text>
              <View style={styles.rowFooter}>
                <Stepper
                  value={item.quantity}
                  min={item.minimum_order_qty}
                  max={item.available_quantity}
                  onChange={(next) => setQuantity(item, next)}
                />
                <Text style={{ color: t.primary, fontWeight: '700' }}>{money(item.subtotal)}</Text>
              </View>
            </View>
            <Pressable
              accessibilityRole="button"
              accessibilityLabel={`Remove ${item.name}`}
              onPress={() => remove(item)}
              hitSlop={8}
              style={{ padding: 4 }}
            >
              <Ionicons name="trash-outline" size={18} color={t.muted} />
            </Pressable>
          </View>
        )}
      />

      <View style={[styles.summary, { borderColor: t.border, backgroundColor: t.surface }]}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginBottom: 12 }}>
          <Text style={{ color: t.muted }}>Total ({items.length} items)</Text>
          <Text style={{ color: t.text, fontWeight: '800', fontSize: 18 }}>{money(total)}</Text>
        </View>
        <Button label="Checkout" icon="arrow-forward" onPress={() => navigation.navigate('Checkout')} />
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 24 },
  row: {
    flexDirection: 'row',
    gap: 12,
    borderWidth: 1,
    borderRadius: 14,
    padding: 12,
    marginBottom: 12,
  },
  thumb: { width: 64, height: 64, borderRadius: 10 },
  thumbFallback: { alignItems: 'center', justifyContent: 'center' },
  rowFooter: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: 8,
  },
  summary: { borderTopWidth: 1, padding: 16, paddingBottom: 24 },
});
