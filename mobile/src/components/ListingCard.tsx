import { Ionicons } from '@expo/vector-icons';
import { Image, Pressable, StyleSheet, Text, View } from 'react-native';
import { imageUrl, type Listing } from '../api';
import { money, useTheme } from '../theme';

export function ListingCard({ listing, onPress }: { listing: Listing; onPress: () => void }) {
  const t = useTheme();
  const uri = imageUrl(listing.image);

  return (
    <Pressable
      accessibilityRole="button"
      accessibilityLabel={`${listing.name}, ${money(listing.price)} per ${listing.unit}`}
      onPress={onPress}
      style={({ pressed }) => [
        styles.card,
        { backgroundColor: t.canvas, borderColor: t.border, opacity: pressed ? 0.85 : 1 },
      ]}
    >
      <View style={[styles.imageWrap, { backgroundColor: t.surface }]}>
        {uri ? (
          <Image source={{ uri }} style={styles.image} resizeMode="cover" />
        ) : (
          <Ionicons name="leaf-outline" size={28} color={t.muted} />
        )}
        {listing.is_organic ? (
          <View style={[styles.organic, { backgroundColor: t.primary }]}>
            <Text style={{ color: t.onPrimary, fontSize: 10, fontWeight: '700' }}>ORGANIC</Text>
          </View>
        ) : null}
        {listing.is_expired ? (
          <View style={[styles.organic, { backgroundColor: t.danger }]}>
            <Text style={{ color: '#fff', fontSize: 10, fontWeight: '700' }}>EXPIRED</Text>
          </View>
        ) : null}
      </View>

      <View style={{ padding: 10, gap: 3 }}>
        <Text numberOfLines={2} style={{ color: t.text, fontWeight: '600', fontSize: 14 }}>
          {listing.name}
        </Text>
        <Text style={{ color: t.primary, fontWeight: '700', fontSize: 15 }}>
          {money(listing.price)}
          <Text style={{ color: t.muted, fontWeight: '400', fontSize: 12 }}> / {listing.unit}</Text>
        </Text>
        <Text numberOfLines={1} style={{ color: t.muted, fontSize: 12 }}>
          {listing.farm_name}
        </Text>
      </View>
    </Pressable>
  );
}

const styles = StyleSheet.create({
  card: { flex: 1, borderWidth: 1, borderRadius: 14, overflow: 'hidden', marginBottom: 12 },
  imageWrap: { aspectRatio: 4 / 3, alignItems: 'center', justifyContent: 'center' },
  image: { width: '100%', height: '100%' },
  organic: { position: 'absolute', top: 8, left: 8, borderRadius: 4, paddingHorizontal: 6, paddingVertical: 2 },
});
