import { Ionicons } from '@expo/vector-icons';
import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Image,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
  useWindowDimensions,
} from 'react-native';
import { api, imageUrl, type ListingDetail } from '../api';
import type { RootStackParams } from '../navigation';
import { useSession } from '../session';
import { date, money, useTheme } from '../theme';
import { Button, ErrorNote, Loading, OfflineBanner, Stepper } from '../ui';

type Props = NativeStackScreenProps<RootStackParams, 'Listing'>;

export function ListingScreen({ route, navigation }: Props) {
  const { id } = route.params;
  const t = useTheme();
  const { width } = useWindowDimensions();
  const { session } = useSession();
  const [listing, setListing] = useState<ListingDetail | null>(null);
  const [stale, setStale] = useState(false);
  const [quantity, setQuantity] = useState(1);
  const [saved, setSaved] = useState(false);
  const [saving, setSaving] = useState(false);
  const [adding, setAdding] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    api
      .listing(id)
      .then((res) => {
        setListing(res.listing);
        setStale(res.stale);
        setQuantity(res.listing.min_order);
        setSaved(res.listing.is_saved);
      })
      .catch((e) => setError(e instanceof Error ? e.message : 'Could not load this listing.'));
  }, [id]);

  const toggleSave = async () => {
    if (!listing) return;
    setSaving(true);
    try {
      const res = await api.toggleSaved(listing.id);
      setSaved(res.saved);
    } catch (e) {
      Alert.alert('Could not update saved items', e instanceof Error ? e.message : 'Try again.');
    } finally {
      setSaving(false);
    }
  };

  const addToCart = async () => {
    if (!listing) return;
    setAdding(true);
    try {
      await api.addToCart(listing.id, quantity);
      Alert.alert('Added to cart', `${quantity} ${listing.unit} of ${listing.name}`, [
        { text: 'Keep browsing', style: 'cancel' },
        { text: 'View cart', onPress: () => navigation.navigate('Tabs', { screen: 'Cart' }) },
      ]);
    } catch (e) {
      Alert.alert('Could not add to cart', e instanceof Error ? e.message : 'Try again.');
    } finally {
      setAdding(false);
    }
  };

  if (error) {
    return (
      <View style={[styles.fill, { backgroundColor: t.canvas }]}>
        <ErrorNote message={error} />
      </View>
    );
  }

  if (!listing) return <Loading label="Loading listing" />;

  const maxQuantity = listing.max_order
    ? Math.min(listing.max_order, listing.available_qty)
    : listing.available_qty;
  const soldOut = listing.available_qty <= 0;

  return (
    <ScrollView style={{ backgroundColor: t.canvas }} contentContainerStyle={{ paddingBottom: 32 }}>
      <View style={{ backgroundColor: t.surface }}>
        {listing.images.length > 0 ? (
          <ScrollView horizontal pagingEnabled showsHorizontalScrollIndicator={false}>
            {listing.images.map((path) => (
              <Image
                key={path}
                source={{ uri: imageUrl(path) }}
                style={{ width, height: width * 0.75 }}
                resizeMode="cover"
              />
            ))}
          </ScrollView>
        ) : (
          <View style={{ width, height: width * 0.75, alignItems: 'center', justifyContent: 'center' }}>
            <Ionicons name="leaf-outline" size={48} color={t.muted} />
          </View>
        )}
      </View>

      <View style={{ padding: 16 }}>
        <OfflineBanner visible={stale} />
        <View style={{ flexDirection: 'row', alignItems: 'flex-start', gap: 12 }}>
          <View style={{ flex: 1 }}>
            <Text style={{ color: t.muted, fontSize: 13 }}>{listing.category}</Text>
            <Text style={{ color: t.text, fontSize: 22, fontWeight: '700', marginTop: 2 }}>
              {listing.name}
            </Text>
            <Text style={{ color: t.primary, fontSize: 22, fontWeight: '800', marginTop: 6 }}>
              {money(listing.price)}
              <Text style={{ color: t.muted, fontSize: 14, fontWeight: '400' }}> / {listing.unit}</Text>
            </Text>
          </View>
          <Pressable
            accessibilityRole="button"
            accessibilityLabel={saved ? 'Remove from saved' : 'Save listing'}
            onPress={toggleSave}
            disabled={saving}
            style={[styles.saveButton, { borderColor: t.border }]}
          >
            {saving ? (
              <ActivityIndicator size="small" color={t.primary} />
            ) : (
              <Ionicons
                name={saved ? 'heart' : 'heart-outline'}
                size={20}
                color={saved ? t.danger : t.muted}
              />
            )}
          </Pressable>
        </View>

        {listing.is_organic ? (
          <View style={[styles.tag, { borderColor: t.accent }]}>
            <Text style={{ color: t.accent, fontSize: 12, fontWeight: '600' }}>Organic</Text>
          </View>
        ) : null}
        {listing.is_expired ? (
          <Text style={{ color: t.danger, marginTop: 8 }}>
            Past its expiry date — check with the farmer before ordering.
          </Text>
        ) : null}

        <View style={[styles.card, { borderColor: t.border, backgroundColor: t.surface }]}>
          <Text style={{ color: t.text, fontWeight: '700', marginBottom: 10 }}>Farm</Text>
          <Text style={{ color: t.text, fontSize: 15, fontWeight: '600' }}>
            {listing.seller.farm_name}
          </Text>
          <Text style={{ color: t.muted, marginTop: 2 }}>
            {[listing.seller.barangay, listing.seller.municipality, listing.seller.province]
              .filter(Boolean)
              .join(', ') || 'Location not provided'}
          </Text>
          <View style={{ flexDirection: 'row', alignItems: 'center', marginTop: 8, gap: 6 }}>
            <Ionicons
              name={listing.seller.verified ? 'shield-checkmark' : 'shield-outline'}
              size={16}
              color={listing.seller.verified ? t.accent : t.muted}
            />
            <Text style={{ color: listing.seller.verified ? t.accent : t.muted, fontSize: 13 }}>
              {listing.seller.verified ? 'Verified farmer' : 'Not yet verified'}
            </Text>
          </View>
        </View>

        <View style={[styles.card, { borderColor: t.border }]}>
          <Row label="Available" value={`${listing.available_qty} ${listing.unit}`} />
          <Row label="Minimum order" value={`${listing.min_order} ${listing.unit}`} />
          {listing.max_order ? (
            <Row label="Maximum order" value={`${listing.max_order} ${listing.unit}`} />
          ) : null}
          {listing.harvest_date ? (
            <Row label="Harvested" value={date(listing.harvest_date)} />
          ) : null}
          {listing.expiry_date ? <Row label="Best before" value={date(listing.expiry_date)} /> : null}
        </View>

        {listing.description ? (
          <View style={{ marginBottom: 16 }}>
            <Text style={{ color: t.text, fontWeight: '700', marginBottom: 6 }}>About this produce</Text>
            <Text style={{ color: t.text, lineHeight: 21 }}>{listing.description}</Text>
          </View>
        ) : null}

        {session?.user.role !== 'farmer' ? (
          <View style={[styles.buyBox, { borderColor: t.border, backgroundColor: t.surface }]}>
            <View style={{ flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' }}>
              <Text style={{ color: t.text, fontWeight: '600' }}>Quantity ({listing.unit})</Text>
              <Stepper
                value={quantity}
                min={listing.min_order}
                max={Math.max(maxQuantity, listing.min_order)}
                onChange={setQuantity}
              />
            </View>
            <Text style={{ color: t.muted, marginTop: 10 }}>
              Subtotal {money(listing.price * quantity)}
            </Text>
            <View style={{ marginTop: 12 }}>
              <Button
                label={soldOut ? 'Sold out' : 'Add to cart'}
                icon="cart-outline"
                onPress={addToCart}
                busy={adding}
                disabled={soldOut}
              />
            </View>
          </View>
        ) : null}
      </View>
    </ScrollView>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  const t = useTheme();
  return (
    <View style={{ flexDirection: 'row', justifyContent: 'space-between', paddingVertical: 4 }}>
      <Text style={{ color: t.muted }}>{label}</Text>
      <Text style={{ color: t.text, fontWeight: '500' }}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 24 },
  saveButton: {
    width: 44,
    height: 44,
    borderRadius: 22,
    borderWidth: 1,
    alignItems: 'center',
    justifyContent: 'center',
  },
  tag: { alignSelf: 'flex-start', borderWidth: 1, borderRadius: 999, paddingHorizontal: 10, paddingVertical: 3, marginTop: 10 },
  card: { borderWidth: 1, borderRadius: 14, padding: 16, marginTop: 16 },
  buyBox: { borderWidth: 1, borderRadius: 14, padding: 16, marginTop: 4 },
});
