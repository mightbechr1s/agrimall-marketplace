import type { NativeStackScreenProps } from '@react-navigation/native-stack';
import { useCallback, useEffect, useState } from 'react';
import {
  Alert,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { api, type OrderAction, type OrderDetail, type OrderMessage, type OrderStatus } from '../api';
import type { RootStackParams } from '../navigation';
import { useSession } from '../session';
import { date, money, useTheme } from '../theme';
import { Button, ErrorNote, Field, Loading, StatusBadge, statusLabels } from '../ui';

type Props = NativeStackScreenProps<RootStackParams, 'OrderDetail'>;
type HistoryEntry = { status: OrderStatus; created_at: string; note: string | null };

export function OrderDetailScreen({ route }: Props) {
  const { id } = route.params;
  const t = useTheme();
  const { session } = useSession();
  const [order, setOrder] = useState<OrderDetail | null>(null);
  const [history, setHistory] = useState<HistoryEntry[]>([]);
  const [messages, setMessages] = useState<OrderMessage[]>([]);
  const [draft, setDraft] = useState('');
  const [sending, setSending] = useState(false);
  const [busy, setBusy] = useState(false);
  const [fee, setFee] = useState('');
  const [reason, setReason] = useState('');
  const [error, setError] = useState('');

  const load = useCallback(async () => {
    try {
      const res = await api.order(id);
      setOrder(res.order);
      setHistory(res.history);
      setMessages(res.messages);
      setError('');
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Could not load this order.');
    }
  }, [id]);

  useEffect(() => {
    load();
  }, [load]);

  const send = async () => {
    const body = draft.trim();
    if (!body || sending) return;
    setSending(true);
    try {
      await api.sendMessage(id, body);
      setDraft('');
      await load();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Message not sent.');
    } finally {
      setSending(false);
    }
  };

  const act = (action: OrderAction, extra: { delivery_fee?: number; reason?: string } = {}) => {
    if (!order) return;
    Alert.alert(
      'Update order?',
      'The buyer and farmer both see this change.',
      [
        { text: 'Cancel', style: 'cancel' },
        {
          text: 'Confirm',
          onPress: async () => {
            setBusy(true);
            try {
              await api.orderAction(order.id, action, extra);
              setReason('');
              setFee('');
              await load();
            } catch (e) {
              Alert.alert('Could not update', e instanceof Error ? e.message : 'Try again.');
            } finally {
              setBusy(false);
            }
          },
        },
      ],
    );
  };

  if (error && !order) {
    return (
      <View style={[styles.fill, { backgroundColor: t.canvas }]}>
        <ErrorNote message={error} onRetry={load} />
      </View>
    );
  }

  if (!order) return <Loading label="Loading order" />;

  const role = session?.user.role;
  const isFarmer = role === 'farmer';
  const isBuyer = role === 'buyer';
  const status = order.status;
  const needsFee = isFarmer && status === 'delivery_fee_review' && order.delivery_method === 'farmer_delivery';
  const canConfirm = isFarmer && (status === 'pending' || status === 'delivery_fee_review');
  const canReject = canConfirm;
  const canPrepare = isFarmer && status === 'confirmed';
  const canReady = isFarmer && status === 'preparing';
  const canShip = isFarmer && status === 'ready_for_pickup';
  const canDeliver =
    isFarmer && status === 'out_for_delivery' && order.delivery_method === 'farmer_delivery';
  const canCancel =
    (isBuyer && ['pending', 'delivery_fee_review', 'confirmed', 'preparing'].includes(status)) ||
    (isFarmer && ['confirmed', 'preparing'].includes(status));
  const canCollect = isBuyer && status === 'ready_for_pickup' && order.delivery_method === 'pickup';

  const confirmOrder = () => {
    const parsed = Number(fee.replace(',', '.'));
    if (order.delivery_method === 'farmer_delivery' && (!Number.isFinite(parsed) || parsed < 0)) {
      Alert.alert('Delivery fee needed', 'Enter the delivery fee you will charge.');
      return;
    }
    act('confirm', {
      delivery_fee: order.delivery_method === 'farmer_delivery' ? parsed : undefined,
      reason: reason.trim() || undefined,
    });
  };

  return (
    <KeyboardAvoidingView
      style={{ flex: 1, backgroundColor: t.canvas }}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      keyboardVerticalOffset={90}
    >
      <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 24 }}>
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' }}>
          <Text style={{ color: t.muted }}>{order.order_number}</Text>
          <StatusBadge status={order.status} />
        </View>
        <Text style={{ color: t.text, fontSize: 20, fontWeight: '700', marginTop: 8 }}>
          {order.listing_name}
        </Text>
        <Text style={{ color: t.muted, marginTop: 2 }}>
          {order.quantity} {order.unit} · {money(order.unit_price)} / {order.unit}
        </Text>

        <View style={[styles.card, { borderColor: t.border, backgroundColor: t.surface }]}>
          <Row label={isFarmer ? 'Buyer' : 'Farm'} value={isFarmer ? order.buyer_name : order.farm_name} />
          {isFarmer ? <Row label="Buyer note" value={order.buyer_note ?? '—'} /> : null}
          <Row
            label="Fulfilment"
            value={order.delivery_method === 'farmer_delivery' ? 'Farmer delivery' : 'Farm pickup'}
          />
          {order.delivery_address ? <Row label="Address" value={order.delivery_address} /> : null}
          {order.preferred_pickup_date ? (
            <Row label="Preferred date" value={date(order.preferred_pickup_date)} />
          ) : null}
          {order.preferred_pickup_time ? (
            <Row label="Preferred time" value={order.preferred_pickup_time} />
          ) : null}
          <Row label="Delivery fee" value={money(order.delivery_fee)} />
          <Row label="Total" value={money(order.total)} strong />
        </View>

        {order.buyer_note && !isFarmer ? (
          <View style={[styles.card, { borderColor: t.border }]}>
            <Text style={{ color: t.muted, fontSize: 12, marginBottom: 4 }}>Your note</Text>
            <Text style={{ color: t.text }}>{order.buyer_note}</Text>
          </View>
        ) : null}

        {(canConfirm || canPrepare || canReady || canShip || canDeliver || canCancel || canCollect) && (
          <View style={[styles.card, { borderColor: t.border }]}>
            <Text style={{ color: t.text, fontWeight: '700', marginBottom: 10 }}>
              {isFarmer ? 'Manage this order' : 'Order options'}
            </Text>

            {needsFee ? (
              <Field
                label="Delivery fee you will charge"
                value={fee}
                onChangeText={setFee}
                keyboardType="decimal-pad"
                placeholder="0.00"
              />
            ) : null}

            {canReject || canCancel ? (
              <Field
                label="Reason (optional, shared with the other party)"
                value={reason}
                onChangeText={setReason}
                placeholder={canReject ? 'Why can you not fulfil this?' : 'Why cancel?'}
                multiline
              />
            ) : null}

            <View style={{ gap: 10 }}>
              {canConfirm ? <Button label="Confirm order" icon="checkmark" onPress={confirmOrder} busy={busy} /> : null}
              {canPrepare ? (
                <Button label="Start preparing" icon="basket-outline" onPress={() => act('prepare')} busy={busy} />
              ) : null}
              {canReady ? (
                <Button label="Mark ready" icon="cube-outline" onPress={() => act('ready')} busy={busy} />
              ) : null}
              {canShip ? (
                <Button label="Out for delivery" icon="car-outline" onPress={() => act('ship')} busy={busy} />
              ) : null}
              {canDeliver ? (
                <Button label="Mark delivered" icon="checkmark-done" onPress={() => act('complete')} busy={busy} />
              ) : null}
              {canCollect ? (
                <Button
                  label="I picked it up"
                  icon="bag-check-outline"
                  onPress={() => act('complete')}
                  busy={busy}
                />
              ) : null}
              {canReject ? (
                <Button
                  label="Reject order"
                  variant="danger"
                  onPress={() => act('reject', { reason: reason.trim() || undefined })}
                  disabled={busy}
                />
              ) : null}
              {canCancel ? (
                <Button
                  label="Cancel order"
                  variant="danger"
                  onPress={() => act('cancel', { reason: reason.trim() || undefined })}
                  disabled={busy}
                />
              ) : null}
            </View>

            <Text style={{ color: t.muted, fontSize: 12, marginTop: 10 }}>
              Stock is deducted when you confirm. Payment happens directly between buyer and farmer.
            </Text>
          </View>
        )}

        {history.length > 0 ? (
          <View style={{ marginTop: 8, marginBottom: 8 }}>
            <Text style={{ color: t.text, fontWeight: '700', marginBottom: 8 }}>Progress</Text>
            {history.map((entry, index) => (
              <View key={`${entry.status}-${index}`} style={styles.timelineRow}>
                <View style={[styles.dot, { backgroundColor: t.accent }]} />
                <View style={{ flex: 1 }}>
                  <Text style={{ color: t.text, fontWeight: '500' }}>
                    {statusLabels[entry.status] ?? entry.status}
                  </Text>
                  <Text style={{ color: t.muted, fontSize: 12 }}>{date(entry.created_at)}</Text>
                  {entry.note ? <Text style={{ color: t.muted, fontSize: 12 }}>{entry.note}</Text> : null}
                </View>
              </View>
            ))}
          </View>
        ) : null}

        <Text style={{ color: t.text, fontWeight: '700', marginTop: 12, marginBottom: 8 }}>Messages</Text>
        {messages.length === 0 ? (
          <Text style={{ color: t.muted }}>
            No messages yet. Ask about pickup, packaging, or payment.
          </Text>
        ) : (
          messages.map((message) => {
            const mine = message.sender_id === session?.user.id;
            return (
              <View
                key={message.id}
                style={[
                  styles.bubble,
                  {
                    alignSelf: mine ? 'flex-end' : 'flex-start',
                    backgroundColor: mine ? t.primary : t.surface,
                    borderColor: mine ? t.primary : t.border,
                  },
                ]}
              >
                <Text style={{ color: mine ? t.onPrimary : t.text }}>{message.body}</Text>
                <Text
                  style={{
                    color: mine ? t.onPrimary : t.muted,
                    fontSize: 10,
                    marginTop: 4,
                    opacity: 0.8,
                  }}
                >
                  {message.sender_role} · {date(message.created_at)}
                </Text>
              </View>
            );
          })
        )}

        {error ? <Text style={{ color: t.danger, marginTop: 8 }}>{error}</Text> : null}
      </ScrollView>

      <View style={[styles.composer, { borderColor: t.border, backgroundColor: t.canvas }]}>
        <TextInput
          value={draft}
          onChangeText={setDraft}
          placeholder="Write a message"
          placeholderTextColor={t.muted}
          style={[styles.input, { borderColor: t.border, color: t.text, backgroundColor: t.surface }]}
          multiline
        />
        <Button label="Send" onPress={send} busy={sending} disabled={!draft.trim()} />
      </View>
    </KeyboardAvoidingView>
  );
}

function Row({ label, value, strong }: { label: string; value: string; strong?: boolean }) {
  const t = useTheme();
  return (
    <View style={styles.row}>
      <Text style={{ color: t.muted, flex: 1 }}>{label}</Text>
      <Text
        style={{
          color: t.text,
          fontWeight: strong ? '800' : '500',
          flexShrink: 1,
          textAlign: 'right',
        }}
      >
        {value}
      </Text>
    </View>
  );
}

const styles = StyleSheet.create({
  fill: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 24 },
  card: { borderWidth: 1, borderRadius: 14, padding: 14, marginTop: 16 },
  row: { flexDirection: 'row', gap: 12, paddingVertical: 4 },
  timelineRow: { flexDirection: 'row', gap: 10, marginBottom: 10 },
  dot: { width: 8, height: 8, borderRadius: 4, marginTop: 6 },
  bubble: { borderWidth: 1, borderRadius: 12, padding: 10, marginBottom: 8, maxWidth: '85%' },
  composer: {
    flexDirection: 'row',
    gap: 10,
    alignItems: 'flex-end',
    padding: 12,
    borderTopWidth: 1,
  },
  input: { flex: 1, borderWidth: 1, borderRadius: 10, padding: 10, maxHeight: 100 },
});
