import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect } from '@react-navigation/native';
import { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Image,
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  RefreshControl,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { api, imageUrl, type FarmerListing } from '../api';
import { money, useTheme } from '../theme';
import { Button, Empty, ErrorNote, Field, OfflineBanner } from '../ui';

const statusTone: Record<FarmerListing['status'], string> = {
  active: 'accent',
  paused: 'gold',
  sold_out: 'muted',
  draft: 'muted',
  archived: 'muted',
  admin_deactivated: 'danger',
};

export function FarmerListingsScreen() {
  const t = useTheme();
  const [listings, setListings] = useState<FarmerListing[]>([]);
  const [stale, setStale] = useState(false);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');
  const [editing, setEditing] = useState<FarmerListing | null>(null);
  const [price, setPrice] = useState('');
  const [quantity, setQuantity] = useState('');
  const [saving, setSaving] = useState(false);

  const load = useCallback(async () => {
    try {
      const res = await api.farmerListings();
      setListings(res.listings);
      setStale(res.stale);
      setError('');
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not load your listings.');
    } finally {
      setLoading(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      load();
    }, [load]),
  );

  const refresh = () => {
    setRefreshing(true);
    load().finally(() => setRefreshing(false));
  };

  const openEditor = (listing: FarmerListing) => {
    setEditing(listing);
    setPrice(String(listing.price));
    setQuantity(String(listing.available_qty));
  };

  const save = async () => {
    if (!editing) return;
    const nextPrice = Number(price.replace(',', '.'));
    const nextQty = Number(quantity.replace(',', '.'));
    if (!Number.isFinite(nextPrice) || nextPrice <= 0) {
      Alert.alert('Invalid price', 'Enter a price greater than zero.');
      return;
    }
    if (!Number.isFinite(nextQty) || nextQty < 0) {
      Alert.alert('Invalid quantity', 'Enter a quantity of zero or more.');
      return;
    }
    setSaving(true);
    try {
      await api.updateFarmerListing({ id: editing.id, price: nextPrice, quantity: nextQty });
      setEditing(null);
      await load();
    } catch (e) {
      Alert.alert('Could not save', e instanceof Error ? e.message : 'Try again.');
    } finally {
      setSaving(false);
    }
  };

  const togglePause = async (listing: FarmerListing) => {
    const next = listing.status === 'active' ? 'paused' : 'active';
    try {
      await api.updateFarmerListing({ id: listing.id, status: next });
      await load();
    } catch (e) {
      Alert.alert('Could not update', e instanceof Error ? e.message : 'Try again.');
    }
  };

  if (loading) {
    return (
      <View style={[styles.fill, { backgroundColor: t.canvas }]}>
        <ActivityIndicator color={t.primary} />
      </View>
    );
  }

  return (
    <View style={{ flex: 1, backgroundColor: t.canvas }}>
      {error ? (
        <View style={{ padding: 16 }}>
          <ErrorNote message={error} onRetry={load} />
        </View>
      ) : null}
      <FlatList
        data={listings}
        keyExtractor={(item) => String(item.id)}
        contentContainerStyle={{ padding: 16 }}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={refresh} tintColor={t.primary} />}
        ListHeaderComponent={
          <>
            <OfflineBanner visible={stale} />
            <Text style={{ color: t.muted, marginBottom: 12 }}>
              {listings.length} {listings.length === 1 ? 'listing' : 'listings'} · tap edit to change price
              or stock. New produce is added on the website.
            </Text>
          </>
        }
        ListEmptyComponent={
          <Empty
            icon="pricetags-outline"
            title="No listings yet"
            hint="Create your first listing on the AgriMALL website, then manage it here."
          />
        }
        renderItem={({ item }) => {
          const tone = statusTone[item.status];
          const color =
            tone === 'accent' ? t.accent : tone === 'gold' ? t.gold : tone === 'danger' ? t.danger : t.muted;
          const uri = imageUrl(item.image);
          const editable = item.status !== 'archived' && item.status !== 'admin_deactivated';
          return (
            <View style={[styles.card, { borderColor: t.border }]}>
              <View style={styles.row}>
                {uri ? (
                  <Image source={{ uri }} style={styles.thumb} />
                ) : (
                  <View style={[styles.thumb, styles.thumbFallback, { backgroundColor: t.surface }]}>
                    <Ionicons name="leaf-outline" size={22} color={t.muted} />
                  </View>
                )}
                <View style={{ flex: 1 }}>
                  <Text style={{ color: t.text, fontWeight: '600' }} numberOfLines={2}>
                    {item.name}
                  </Text>
                  <Text style={{ color: t.muted, fontSize: 12, marginTop: 2 }}>
                    {item.category} · {money(item.price)} / {item.unit}
                  </Text>
                  <Text style={{ color: t.muted, fontSize: 12, marginTop: 2 }}>
                    {item.available_qty} {item.unit} in stock
                  </Text>
                  <View style={[styles.chip, { borderColor: color }]}>
                    <Text style={{ color, fontSize: 11, fontWeight: '700', textTransform: 'uppercase' }}>
                      {item.status.replace('_', ' ')}
                    </Text>
                  </View>
                </View>
              </View>

              {item.is_expired ? (
                <Text style={{ color: t.danger, fontSize: 12, marginTop: 8 }}>
                  Past its expiry date — buyers see a warning.
                </Text>
              ) : null}
              {item.rejection_reason ? (
                <Text style={{ color: t.danger, fontSize: 12, marginTop: 8 }}>
                  {item.rejection_reason}
                </Text>
              ) : null}

              {editable ? (
                <View style={styles.actions}>
                  <Button
                    label="Edit price & stock"
                    variant="secondary"
                    icon="create-outline"
                    onPress={() => openEditor(item)}
                  />
                  {item.status === 'active' || item.status === 'paused' ? (
                    <Button
                      label={item.status === 'active' ? 'Pause' : 'Publish'}
                      variant="ghost"
                      onPress={() => togglePause(item)}
                    />
                  ) : null}
                </View>
              ) : null}
            </View>
          );
        }}
      />

      <Modal visible={editing !== null} animationType="slide" transparent onRequestClose={() => setEditing(null)}>
        <KeyboardAvoidingView
          style={styles.modalBackdrop}
          behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        >
          <View style={[styles.modalCard, { backgroundColor: t.canvas, borderColor: t.border }]}>
            <Text style={{ color: t.text, fontSize: 18, fontWeight: '700', marginBottom: 4 }}>
              {editing?.name}
            </Text>
            <Text style={{ color: t.muted, marginBottom: 16 }}>
              Price per {editing?.unit} and available stock.
            </Text>
            <Field
              label={`Price per ${editing?.unit ?? 'unit'}`}
              value={price}
              onChangeText={setPrice}
              keyboardType="decimal-pad"
            />
            <Field
              label="Available stock"
              value={quantity}
              onChangeText={setQuantity}
              keyboardType="decimal-pad"
            />
            <View style={{ flexDirection: 'row', gap: 10 }}>
              <View style={{ flex: 1 }}>
                <Button label="Cancel" variant="ghost" onPress={() => setEditing(null)} />
              </View>
              <View style={{ flex: 1 }}>
                <Button label="Save" onPress={save} busy={saving} />
              </View>
            </View>
          </View>
        </KeyboardAvoidingView>
      </Modal>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  card: { borderWidth: 1, borderRadius: 14, padding: 12, marginBottom: 12 },
  row: { flexDirection: 'row', gap: 12 },
  thumb: { width: 64, height: 64, borderRadius: 10 },
  thumbFallback: { alignItems: 'center', justifyContent: 'center' },
  chip: { alignSelf: 'flex-start', borderWidth: 1, borderRadius: 999, paddingHorizontal: 8, paddingVertical: 2, marginTop: 6 },
  actions: { flexDirection: 'row', gap: 10, marginTop: 12, alignItems: 'center' },
  modalBackdrop: { flex: 1, justifyContent: 'flex-end', backgroundColor: 'rgba(0,0,0,0.4)' },
  modalCard: { borderWidth: 1, borderTopLeftRadius: 20, borderTopRightRadius: 20, padding: 20, paddingBottom: 32 },
});
