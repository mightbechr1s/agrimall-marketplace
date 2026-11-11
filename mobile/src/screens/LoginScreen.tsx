import { Ionicons } from '@expo/vector-icons';
import { useState } from 'react';
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
import { API_ORIGIN, api, normalizeApiBase, saveApiBase, type Role } from '../api';
import { useSession } from '../session';
import { useTheme } from '../theme';
import { Button, Field } from '../ui';

type Mode = 'login' | 'register' | 'otp';

const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export function LoginScreen() {
  const t = useTheme();
  const { signIn } = useSession();
  const [mode, setMode] = useState<Mode>('login');
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [totpCode, setTotpCode] = useState('');
  const [needsTotp, setNeedsTotp] = useState(false);
  const [role, setRole] = useState<Role>('buyer');
  const [code, setCode] = useState('');
  const [devCode, setDevCode] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const [showServer, setShowServer] = useState(false);
  const [server, setServer] = useState(API_ORIGIN);
  const [serverLabel, setServerLabel] = useState(API_ORIGIN);

  const run = async (task: () => Promise<void>) => {
    setError('');
    setBusy(true);
    try {
      await task();
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Something went wrong.');
    } finally {
      setBusy(false);
    }
  };

  const login = () =>
    run(async () => {
      if (!emailPattern.test(email.trim())) throw new Error('Enter a valid email address.');
      if (!password) throw new Error('Enter your password.');
      if (needsTotp && !/^\d{6}$/.test(totpCode.trim())) throw new Error('Enter the 6-digit code.');
      try {
        const res = await api.login(email.trim(), password, needsTotp ? totpCode.trim() : undefined);
        setNeedsTotp(false);
        setTotpCode('');
        await signIn(res.token, res.user);
      } catch (e) {
        const data = (e as { data?: { totp_required?: boolean } })?.data;
        if (data?.totp_required) {
          setNeedsTotp(true);
          setError('Enter the 6-digit code from your authenticator app.');
          return;
        }
        throw e;
      }
    });

  const register = () =>
    run(async () => {
      if (name.trim().length < 2) throw new Error('Enter your full name.');
      if (!emailPattern.test(email.trim())) throw new Error('Enter a valid email address.');
      if (password.length < 8) throw new Error('Password must be at least 8 characters.');
      const res = await api.register({
        full_name: name.trim(),
        email: email.trim(),
        password,
        role,
      });
      setDevCode(res.dev_code);
      setMode('otp');
    });

  const verify = () =>
    run(async () => {
      if (!/^\d{6}$/.test(code.trim())) throw new Error('Enter the 6-digit code.');
      const res = await api.verifyOtp({
        email: email.trim(),
        code: code.trim(),
        full_name: name.trim(),
        password,
        role,
      });
      await signIn(res.token, res.user);
    });

  const switchMode = (next: Mode) => {
    setError('');
    setNeedsTotp(false);
    setTotpCode('');
    setMode(next);
  };

  const applyServer = async () => {
    const next = normalizeApiBase(server);
    if (!next) {
      setError('Enter an address like https://example.trycloudflare.com');
      return;
    }
    await saveApiBase(next);
    setServerLabel(next.replace(/\/api$/, ''));
    setServer(next.replace(/\/api$/, ''));
    setShowServer(false);
    setError('');
  };

  return (
    <KeyboardAvoidingView
      style={{ flex: 1, backgroundColor: t.canvas }}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <ScrollView contentContainerStyle={styles.content} keyboardShouldPersistTaps="handled">
        <View style={styles.brand}>
          <View style={[styles.mark, { backgroundColor: t.primary }]}>
            <Ionicons name="leaf" size={26} color={t.onPrimary} />
          </View>
          <Text style={[styles.wordmark, { color: t.text }]}>AgriMALL</Text>
          <Text style={{ color: t.muted, textAlign: 'center' }}>
            Farm produce, direct from Filipino farmers.
          </Text>
        </View>

        {mode === 'login' ? (
          <>
            <Field
              label="Email"
              value={email}
              onChangeText={setEmail}
              autoCapitalize="none"
              keyboardType="email-address"
              autoComplete="email"
              placeholder="you@example.com"
            />
            <Field
              label="Password"
              value={password}
              onChangeText={setPassword}
              secureTextEntry
              placeholder="••••••••"
            />
            {needsTotp ? (
              <Field
                label="Authenticator code"
                value={totpCode}
                onChangeText={setTotpCode}
                keyboardType="number-pad"
                maxLength={6}
                placeholder="123456"
              />
            ) : null}
            {error ? <Text style={[styles.error, { color: t.danger }]}>{error}</Text> : null}
            <Button label="Sign in" onPress={login} busy={busy} />
            <View style={styles.switchRow}>
              <Text style={{ color: t.muted }}>New to AgriMALL?</Text>
              <Pressable onPress={() => switchMode('register')} hitSlop={8}>
                <Text style={{ color: t.primary, fontWeight: '600' }}> Create an account</Text>
              </Pressable>
            </View>
            <Pressable
              onPress={() => setShowServer((open) => !open)}
              style={styles.serverToggle}
              hitSlop={8}
            >
              <Ionicons name="server-outline" size={14} color={t.muted} />
              <Text style={{ color: t.muted, fontSize: 13 }}> {serverLabel}</Text>
            </Pressable>
            {showServer ? (
              <View style={{ marginTop: 12 }}>
                <Field
                  label="Server address"
                  value={server}
                  onChangeText={setServer}
                  autoCapitalize="none"
                  keyboardType="url"
                  placeholder="https://example.trycloudflare.com"
                />
                <Button label="Use this server" onPress={applyServer} />
              </View>
            ) : null}
          </>
        ) : null}

        {mode === 'register' ? (
          <>
            <Field label="Full name" value={name} onChangeText={setName} placeholder="Juan Dela Cruz" />
            <Field
              label="Email"
              value={email}
              onChangeText={setEmail}
              autoCapitalize="none"
              keyboardType="email-address"
              placeholder="you@example.com"
            />
            <Field
              label="Password"
              value={password}
              onChangeText={setPassword}
              secureTextEntry
              placeholder="At least 8 characters"
            />
            <Text style={{ color: t.muted, fontSize: 13, marginBottom: 6 }}>I want to</Text>
            <View style={styles.roleRow}>
              {(['buyer', 'farmer'] as Role[]).map((option) => (
                <Pressable
                  key={option}
                  onPress={() => setRole(option)}
                  style={[
                    styles.role,
                    {
                      borderColor: role === option ? t.primary : t.border,
                      backgroundColor: role === option ? t.primary : 'transparent',
                    },
                  ]}
                >
                  <Text style={{ color: role === option ? t.onPrimary : t.text, fontWeight: '600' }}>
                    {option === 'buyer' ? 'Buy produce' : 'Sell produce'}
                  </Text>
                </Pressable>
              ))}
            </View>
            {error ? <Text style={[styles.error, { color: t.danger }]}>{error}</Text> : null}
            <Button label="Create account" onPress={register} busy={busy} />
            <View style={styles.switchRow}>
              <Text style={{ color: t.muted }}>Already registered?</Text>
              <Pressable onPress={() => switchMode('login')} hitSlop={8}>
                <Text style={{ color: t.primary, fontWeight: '600' }}> Sign in</Text>
              </Pressable>
            </View>
          </>
        ) : null}

        {mode === 'otp' ? (
          <>
            <Text style={{ color: t.text, fontSize: 18, fontWeight: '700', marginBottom: 6 }}>
              Check your email
            </Text>
            <Text style={{ color: t.muted, marginBottom: 16 }}>
              We sent a 6-digit code to {email}. It expires in 10 minutes.
            </Text>
            <Field
              label="Verification code"
              value={code}
              onChangeText={setCode}
              keyboardType="number-pad"
              maxLength={6}
              placeholder="000000"
            />
            {devCode ? (
              <Text style={{ color: t.gold, marginBottom: 12 }}>Dev code: {devCode}</Text>
            ) : null}
            {error ? <Text style={[styles.error, { color: t.danger }]}>{error}</Text> : null}
            <Button label="Verify and continue" onPress={verify} busy={busy} />
            <Pressable
              style={{ marginTop: 16 }}
              onPress={() =>
                Alert.alert('Code not received?', 'Check your spam folder, then register again.')
              }
            >
              <Text style={{ color: t.muted, textAlign: 'center' }}>Didn't get a code?</Text>
            </Pressable>
          </>
        ) : null}
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  content: { padding: 24, paddingTop: 64, paddingBottom: 48 },
  brand: { alignItems: 'center', gap: 8, marginBottom: 32 },
  mark: { width: 56, height: 56, borderRadius: 16, alignItems: 'center', justifyContent: 'center' },
  wordmark: { fontSize: 28, fontWeight: '800', letterSpacing: 0.5 },
  error: { marginBottom: 12 },
  switchRow: { flexDirection: 'row', justifyContent: 'center', marginTop: 20 },
  serverToggle: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 28,
  },
  roleRow: { flexDirection: 'row', gap: 10, marginBottom: 16 },
  role: { flex: 1, borderWidth: 1, borderRadius: 10, paddingVertical: 12, alignItems: 'center' },
});
