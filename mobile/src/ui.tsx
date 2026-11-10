import { Ionicons } from '@expo/vector-icons';
import type { ReactNode } from 'react';
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
  type TextInputProps,
} from 'react-native';
import type { OrderStatus } from './api';
import { useTheme } from './theme';

export const statusLabels: Record<OrderStatus, string> = {
  pending: 'Pending',
  delivery_fee_review: 'Delivery fee review',
  confirmed: 'Confirmed',
  preparing: 'Preparing',
  ready_for_pickup: 'Ready for pickup',
  out_for_delivery: 'Out for delivery',
  completed: 'Completed',
  rejected: 'Rejected',
  cancelled: 'Cancelled',
};

const statusTones: Record<OrderStatus, 'neutral' | 'good' | 'warn' | 'bad'> = {
  pending: 'warn',
  delivery_fee_review: 'warn',
  confirmed: 'neutral',
  preparing: 'neutral',
  ready_for_pickup: 'good',
  out_for_delivery: 'good',
  completed: 'good',
  rejected: 'bad',
  cancelled: 'bad',
};

export function useStatusColor() {
  const t = useTheme();
  return (status: OrderStatus) => {
    const tone = statusTones[status] ?? 'neutral';
    if (tone === 'good') return t.accent;
    if (tone === 'warn') return t.gold;
    if (tone === 'bad') return t.danger;
    return t.muted;
  };
}

export function StatusBadge({ status }: { status: OrderStatus }) {
  const t = useTheme();
  const color = useStatusColor()(status);
  return (
    <View style={[styles.badge, { borderColor: color }]}>
      <Text style={[styles.badgeText, { color }]}>{statusLabels[status] ?? status}</Text>
    </View>
  );
}

export function Loading({ label }: { label?: string }) {
  const t = useTheme();
  return (
    <View style={styles.center}>
      <ActivityIndicator color={t.primary} />
      {label ? <Text style={{ color: t.muted, marginTop: 8 }}>{label}</Text> : null}
    </View>
  );
}

export function ErrorNote({ message, onRetry }: { message: string; onRetry?: () => void }) {
  const t = useTheme();
  return (
    <View style={[styles.card, { backgroundColor: t.surface, borderColor: t.border }]}>
      <Text style={{ color: t.danger, marginBottom: onRetry ? 8 : 0 }}>{message}</Text>
      {onRetry ? <Button label="Try again" variant="secondary" onPress={onRetry} /> : null}
    </View>
  );
}

export function Empty({ icon = 'leaf-outline', title, hint, action }: {
  icon?: keyof typeof Ionicons.glyphMap;
  title: string;
  hint?: string;
  action?: ReactNode;
}) {
  const t = useTheme();
  return (
    <View style={styles.center}>
      <Ionicons name={icon} size={40} color={t.muted} />
      <Text style={{ color: t.text, fontWeight: '600', fontSize: 16, marginTop: 12 }}>{title}</Text>
      {hint ? <Text style={{ color: t.muted, marginTop: 4, textAlign: 'center' }}>{hint}</Text> : null}
      {action ? <View style={{ marginTop: 16 }}>{action}</View> : null}
    </View>
  );
}

export function OfflineBanner({ visible }: { visible: boolean }) {
  const t = useTheme();
  if (!visible) return null;
  return (
    <View style={[styles.offline, { backgroundColor: t.surface, borderColor: t.border }]}>
      <Ionicons name="cloud-offline-outline" size={14} color={t.muted} />
      <Text style={{ color: t.muted, fontSize: 12 }}>
        Offline — showing the last saved copy.
      </Text>
    </View>
  );
}

export function Button({
  label,
  onPress,
  variant = 'primary',
  disabled,
  busy,
  icon,
}: {
  label: string;
  onPress: () => void;
  variant?: 'primary' | 'secondary' | 'ghost' | 'danger';
  disabled?: boolean;
  busy?: boolean;
  icon?: keyof typeof Ionicons.glyphMap;
}) {
  const t = useTheme();
  const palette = {
    primary: { bg: t.primary, fg: t.onPrimary, border: t.primary },
    secondary: { bg: 'transparent', fg: t.primary, border: t.primary },
    ghost: { bg: 'transparent', fg: t.muted, border: 'transparent' },
    danger: { bg: 'transparent', fg: t.danger, border: t.danger },
  }[variant];

  return (
    <Pressable
      accessibilityRole="button"
      onPress={onPress}
      disabled={disabled || busy}
      style={({ pressed }) => [
        styles.button,
        {
          backgroundColor: palette.bg,
          borderColor: palette.border,
          opacity: disabled || busy ? 0.5 : pressed ? 0.8 : 1,
        },
      ]}
    >
      {busy ? (
        <ActivityIndicator color={palette.fg} />
      ) : (
        <>
          {icon ? <Ionicons name={icon} size={16} color={palette.fg} style={{ marginRight: 6 }} /> : null}
          <Text style={{ color: palette.fg, fontWeight: '600' }}>{label}</Text>
        </>
      )}
    </Pressable>
  );
}

export function Field({
  label,
  error,
  ...props
}: TextInputProps & { label: string; error?: string }) {
  const t = useTheme();
  return (
    <View style={{ marginBottom: 14 }}>
      <Text style={{ color: t.muted, fontSize: 13, marginBottom: 6 }}>{label}</Text>
      <TextInput
        placeholderTextColor={t.muted}
        {...props}
        style={[
          styles.input,
          { backgroundColor: t.surface, borderColor: error ? t.danger : t.border, color: t.text },
          props.style,
        ]}
      />
      {error ? <Text style={{ color: t.danger, fontSize: 12, marginTop: 4 }}>{error}</Text> : null}
    </View>
  );
}

export function Chip({
  label,
  active,
  onPress,
}: {
  label: string;
  active?: boolean;
  onPress: () => void;
}) {
  const t = useTheme();
  return (
    <Pressable
      accessibilityRole="button"
      accessibilityState={{ selected: active }}
      onPress={onPress}
      style={[
        styles.chip,
        {
          backgroundColor: active ? t.primary : t.surface,
          borderColor: active ? t.primary : t.border,
        },
      ]}
    >
      <Text style={{ color: active ? t.onPrimary : t.text, fontSize: 13 }}>{label}</Text>
    </Pressable>
  );
}

export function Stepper({
  value,
  step = 1,
  min,
  max,
  onChange,
}: {
  value: number;
  step?: number;
  min: number;
  max: number;
  onChange: (next: number) => void;
}) {
  const t = useTheme();
  const round = (n: number) => Math.round(n * 1000) / 1000;
  return (
    <View style={[styles.stepper, { borderColor: t.border }]}>
      <Pressable
        accessibilityLabel="Decrease quantity"
        onPress={() => onChange(Math.max(min, round(value - step)))}
        disabled={value <= min}
        style={styles.stepperButton}
      >
        <Ionicons name="remove" size={18} color={value <= min ? t.muted : t.text} />
      </Pressable>
      <Text style={{ color: t.text, minWidth: 44, textAlign: 'center', fontWeight: '600' }}>
        {value}
      </Text>
      <Pressable
        accessibilityLabel="Increase quantity"
        onPress={() => onChange(Math.min(max, round(value + step)))}
        disabled={value >= max}
        style={styles.stepperButton}
      >
        <Ionicons name="add" size={18} color={value >= max ? t.muted : t.text} />
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', padding: 32 },
  card: { borderWidth: 1, borderRadius: 14, padding: 16, marginBottom: 16 },
  badge: {
    borderWidth: 1,
    borderRadius: 999,
    paddingHorizontal: 10,
    paddingVertical: 3,
    alignSelf: 'flex-start',
  },
  badgeText: { fontSize: 12, fontWeight: '600' },
  button: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    borderWidth: 1,
    borderRadius: 10,
    paddingVertical: 13,
    paddingHorizontal: 18,
  },
  input: {
    borderWidth: 1,
    borderRadius: 10,
    paddingHorizontal: 12,
    paddingVertical: 11,
    fontSize: 15,
  },
  chip: {
    borderWidth: 1,
    borderRadius: 999,
    paddingHorizontal: 14,
    paddingVertical: 7,
    marginRight: 8,
  },
  stepper: {
    flexDirection: 'row',
    alignItems: 'center',
    borderWidth: 1,
    borderRadius: 10,
    alignSelf: 'flex-start',
  },
  offline: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 6,
    borderWidth: 1,
    borderRadius: 8,
    paddingHorizontal: 10,
    paddingVertical: 7,
    marginBottom: 12,
  },
  stepperButton: { paddingHorizontal: 12, paddingVertical: 10 },
});
