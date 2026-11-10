import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { useEffect, useState } from 'react';
import {
  Alert,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { api, type CartItem } from '../api';
import type { RootStackParams } from '../navigation';
import { money, useTheme } from '../theme';
import { Button, ErrorNote, Field, Loading } from '../ui';

type Props = NativeStackScreenProps<RootStackParams, 'Checkout'>;
type Method = 'pickup' | 'farmer_delivery';

export function CheckoutScreen({ navigation }: Props) {
  const t = useTheme();
  const [items, setItems] = useState<CartItem[]>([]);
  const [total, setTotal] = useState(0);
  const [method, setMethod] = useState<Method>('pickup');
  const [address, setAddress] = useState('');
  const [note, setNote] = useState('');
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    Promise.all([api.cart(), api.profile()])
      .then(([cart, profile]) => {
        setItems(cart.items);
        setTotal(cart.total);
        setAddress(profile.profile.delivery_address ?? '');
      })
      .catch((e) => setError(e instanceof Error ? e.message : 'Could not load checkout.'))
      .finally(() => setLoading(false));
  }, []);

  const placeOrder = async () => {
    if (items.length === 0) return;
    if (method === 'farmer_delivery' && address.trim().length < 5) {
      setError('Enter a delivery address (at least 5 characters).');
      return;
    }
    setBusy(true);
    setError('');
    try {
      await api.checkout({
        delivery_method: method,
        delivery_address: method === 'farmer_delivery' ? address.trim() : undefined,
        buyer_note: note.trim() || undefined,
      });
      Alert.alert(
        'Order placed',
        method === 'farmer_delivery'
          ? 'The farmer will confirm the delivery fee before preparing your order.'
          : 'The farmer will confirm your order shortly. Arrange payment directly with them on pickup.',
        [
          {
            text: 'View orders',
            onPress: () => navigation.navigate('Tabs', { screen: 'Orders' }),
          },
        ],
      );
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Checkout failed. Try again.');
    } finally {
      setBusy(false);
    }
  };

  if (loading) return <Loading label="Preparing checkout" />;

  return (
    <KeyboardAvoidingView
      style={{ flex: 1, backgroundColor: t.canvas }}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 32 }} keyboardShouldPersistTaps="handled">
        <Text style={[styles.heading, { color: t.text }]}>How would you like to receive it?</Text>
        <View style={{ flexDirection: 'row', gap: 10, marginBottom: 16 }}>
          {(
            [
              { key: 'pickup', label: 'Pick up at farm' },
              { key: 'farmer_delivery', label: 'Farmer delivers' },
            ] as const
          ).map((option) => (
            <Pressable
              key={option.key}
              accessibilityRole="radio"
              accessibilityState={{ selected: method === option.key }}
              onPress={() => setMethod(option.key)}
              style={[
                styles.method,
                {
                  borderColor: method === option.key ? t.primary : t.border,
                  backgroundColor: method === option.key ? t.primary : 'transparent',
                },
              ]}
            >
              <Text
                style={{
                  color: method === option.key ? t.onPrimary : t.text,
                  fontWeight: '600',
                  textAlign: 'center',
                }}
              >
                {option.label}
              </Text>
            </Pressable>
          ))}
        </View>

        {method === 'farmer_delivery' ? (
          <Field
            label="Delivery address"
            value={address}
            onChangeText={setAddress}
            placeholder="House, street, barangay, city"
            multiline
          />
        ) : null}

        <Field
          label="Note for the farmer (optional)"
          value={note}
          onChangeText={setNote}
          placeholder="Preferred pickup date, packaging, etc."
          multiline
        />

        <View style={[styles.card, { borderColor: t.border, backgroundColor: t.surface }]}>
          <Text style={{ color: t.text, fontWeight: '700', marginBottom: 10 }}>
            Order summary
          </Text>
          {items.map((item) => (
            <View key={item.cart_id} style={styles.line}>
              <Text style={{ color: t.text, flex: 1 }} numberOfLines={1}>
                {item.quantity} {item.symbol} · {item.name}
              </Text>
              <Text style={{ color: t.text }}>{money(item.subtotal)}</Text>
            </View>
          ))}
          <View style={[styles.line, { borderTopWidth: 1, borderColor: t.border, paddingTop: 10, marginTop: 6 }]}>
            <Text style={{ color: t.text, fontWeight: '700', flex: 1 }}>Total</Text>
            <Text style={{ color: t.text, fontWeight: '800', fontSize: 17 }}>{money(total)}</Text>
          </View>
          <Text style={{ color: t.muted, fontSize: 12, marginTop: 10 }}>
            AgriMALL does not process payments. Pay the farmer directly on pickup or delivery.
          </Text>
        </View>

        {error ? <ErrorNote message={error} /> : null}

        <Button
          label="Place order"
          onPress={placeOrder}
          busy={busy}
          disabled={items.length === 0}
        />
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  heading: { fontSize: 17, fontWeight: '700', marginBottom: 10 },
  method: { flex: 1, borderWidth: 1, borderRadius: 12, padding: 14 },
  card: { borderWidth: 1, borderRadius: 14, padding: 16, marginBottom: 16 },
  line: { flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 4, gap: 12 },
});
