import { Ionicons } from '@expo/vector-icons';
import { useFocusEffect } from '@react-navigation/native';
import { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  KeyboardAvoidingView,
  Modal,
  Platform,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { api, type AdminApplication } from '../api';
import { date, useTheme } from '../theme';
import { Button, Chip, Empty, ErrorNote, Field, OfflineBanner } from '../ui';

type Filter = 'pending' | 'approved' | 'rejected';

export function AdminVerifyScreen() {
  const t = useTheme();
  const [filter, setFilter] = useState<Filter>('pending');
  const [applications, setApplications] = useState<AdminApplication[]>([]);
  const [pendingTotal, setPendingTotal] = useState(0);
  const [stale, setStale] = useState(false);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState('');
  const [busyId, setBusyId] = useState<number | null>(null);
  const [rejecting, setRejecting] = useState<AdminApplication | null>(null);
  const [reason, setReason] = useState('');

  const load = useCallback(
    async (next: Filter) => {
      try {
        const res = await api.adminApplications(next);
        setApplications(res.applications);
        setPendingTotal(res.pending_total);
        setStale(res.stale);
        setError('');
      } catch (e) {
        setError(e instanceof Error ? e.message : 'Could not load applications.');
      } finally {
        setLoading(false);
      }
    },
    [],
  );

  useFocusEffect(
    useCallback(() => {
      load(filter);
    }, [load, filter]),
  );

  const refresh = () => {
    setRefreshing(true);
    load(filter).finally(() => setRefreshing(false));
  };

  const approve = (application: AdminApplication) => {
    Alert.alert(
      'Approve this farm?',
      `${application.farm_name} will be able to publish produce immediately.`,
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Approve',
          onPress: async () => {
            setBusyId(application.user_id);
            try {
              await api.adminReview(application.user_id, 'approve');
              await load(filter);
            } catch (e) {
              Alert.alert('Could not approve', e instanceof Error ? e.message : 'Try again.');
            } finally {
              setBusyId(null);
            }
          },
        },
      ],
    );
  };

  const reject = async () => {
    if (!rejecting) return;
    if (reason.trim().length < 3) {
      Alert.alert('Reason required', 'Tell the farmer why the application was rejected.');
      return;
    }
    setBusyId(rejecting.user_id);
    try {
      await api.adminReview(rejecting.user_id, 'reject', reason.trim());
      setRejecting(null);
      setReason('');
      await load(filter);
    } catch (e) {
      Alert.alert('Could not reject', e instanceof Error ? e.message : 'Try again.');
    } finally {
      setBusyId(null);
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
      <ScrollView
        horizontal
        showsHorizontalScrollIndicator={false}
        contentContainerStyle={{ paddingHorizontal: 16, paddingTop: 12 }}
      >
        {(['pending', 'approved', 'rejected'] as Filter[]).map((value) => (
          <Chip
            key={value}
            label={
              value === 'pending' && pendingTotal > 0
                ? `Pending (${pendingTotal})`
                : value.charAt(0).toUpperCase() + value.slice(1)
            }
            active={filter === value}
            onPress={() => {
              setLoading(true);
              setFilter(value);
            }}
          />
        ))}
      </ScrollView>

      <FlatList
        data={applications}
        keyExtractor={(item) => String(item.user_id)}
        contentContainerStyle={{ padding: 16, paddingTop: 12 }}
        refreshControl={<RefreshControl refreshing={refreshing} onRefresh={refresh} tintColor={t.primary} />}
        ListHeaderComponent={
          <>
            <OfflineBanner visible={stale} />
            {error ? <ErrorNote message={error} onRetry={refresh} /> : null}
          </>
        }
        ListEmptyComponent={
          error ? undefined : (
            <Empty
              icon="shield-checkmark-outline"
              title={filter === 'pending' ? 'No applications waiting' : 'Nothing here'}
              hint={
                filter === 'pending'
                  ? 'New farmer registrations will appear here for review.'
                  : undefined
              }
            />
          )
        }
        renderItem={({ item }) => {
          const pending = item.verification_status === 'pending';
          return (
            <View style={[styles.card, { borderColor: t.border }]}>
              <View style={{ flexDirection: 'row', justifyContent: 'space-between', gap: 8 }}>
                <Text style={{ color: t.text, fontSize: 16, fontWeight: '700', flex: 1 }}>
                  {item.farm_name}
                </Text>
                <Text style={{ color: t.muted, fontSize: 12 }}>{date(item.registered_at)}</Text>
              </View>
              <Text style={{ color: t.muted, fontSize: 13, marginTop: 2 }}>{item.email}</Text>

              <View style={{ marginTop: 10, gap: 3 }}>
                <Detail icon="person-outline" text={item.contact_person} />
                {item.contact_phone ? <Detail icon="call-outline" text={item.contact_phone} /> : null}
                <Detail icon="location-outline" text={item.location || 'No address'} />
                <Detail icon="card-outline" text={`${item.id_type} · ${item.id_number}`} />
                <Detail
                  icon="document-attach-outline"
                  text={item.has_id_image ? 'ID image on file' : 'No ID image uploaded'}
                />
              </View>

              {item.rejection_reason ? (
                <Text style={{ color: t.danger, fontSize: 12, marginTop: 8 }}>
                  Rejected: {item.rejection_reason}
                </Text>
              ) : null}

              {pending ? (
                <View style={{ flexDirection: 'row', gap: 10, marginTop: 14 }}>
                  <View style={{ flex: 1 }}>
                    <Button
                      label="Approve"
                      icon="checkmark"
                      busy={busyId === item.user_id}
                      onPress={() => approve(item)}
                    />
                  </View>
                  <View style={{ flex: 1 }}>
                    <Button
                      label="Reject"
                      variant="danger"
                      onPress={() => {
                        setReason('');
                        setRejecting(item);
                      }}
                    />
                  </View>
                </View>
              ) : null}
            </View>
          );
        }}
      />

      <Modal
        visible={rejecting !== null}
        animationType="slide"
        transparent
        onRequestClose={() => setRejecting(null)}
      >
        <KeyboardAvoidingView
          style={styles.modalBackdrop}
          behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        >
          <View style={[styles.modalCard, { backgroundColor: t.canvas, borderColor: t.border }]}>
            <Text style={{ color: t.text, fontSize: 18, fontWeight: '700', marginBottom: 4 }}>
              Reject {rejecting?.farm_name}?
            </Text>
            <Text style={{ color: t.muted, marginBottom: 16 }}>
              The farmer sees this reason and can correct the application.
            </Text>
            <Field
              label="Reason"
              value={reason}
              onChangeText={setReason}
              placeholder="e.g. ID photo is unreadable"
              multiline
            />
            <View style={{ flexDirection: 'row', gap: 10 }}>
              <View style={{ flex: 1 }}>
                <Button label="Cancel" variant="ghost" onPress={() => setRejecting(null)} />
              </View>
              <View style={{ flex: 1 }}>
                <Button label="Reject" variant="danger" onPress={reject} busy={busyId !== null} />
              </View>
            </View>
          </View>
        </KeyboardAvoidingView>
      </Modal>
    </View>
  );
}

function Detail({ icon, text }: { icon: keyof typeof Ionicons.glyphMap; text: string }) {
  const t = useTheme();
  return (
    <View style={{ flexDirection: 'row', alignItems: 'center', gap: 6 }}>
      <Ionicons name={icon} size={13} color={t.muted} />
      <Text style={{ color: t.muted, fontSize: 13, flex: 1 }}>{text}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  card: { borderWidth: 1, borderRadius: 14, padding: 14, marginBottom: 12 },
  modalBackdrop: { flex: 1, justifyContent: 'flex-end', backgroundColor: 'rgba(0,0,0,0.4)' },
  modalCard: { borderWidth: 1, borderTopLeftRadius: 20, borderTopRightRadius: 20, padding: 20, paddingBottom: 32 },
});
