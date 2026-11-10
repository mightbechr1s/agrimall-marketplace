import { useColorScheme } from 'react-native';

export type Theme = {
  canvas: string;
  surface: string;
  primary: string;
  accent: string;
  text: string;
  muted: string;
  border: string;
  gold: string;
  danger: string;
  onPrimary: string;
};

const light: Theme = {
  canvas: '#ffffff',
  surface: '#f8f9fa',
  primary: '#1b4332',
  accent: '#2d6a4f',
  text: '#212529',
  muted: '#6c757d',
  border: '#dee2e6',
  gold: '#a67c00',
  danger: '#b02a37',
  onPrimary: '#ffffff',
};

const dark: Theme = {
  canvas: '#121212',
  surface: '#1e1e1e',
  primary: '#52b788',
  accent: '#40916c',
  text: '#f1f3f5',
  muted: '#adb5bd',
  border: '#343a40',
  gold: '#e9c46a',
  danger: '#ff8a8a',
  onPrimary: '#081c15',
};

export function useTheme(): Theme {
  return useColorScheme() === 'dark' ? dark : light;
}

export const money = (n: number) =>
  '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// MySQL returns "YYYY-MM-DD HH:MM:SS"; iOS needs the T separator.
export const date = (s: string | null) =>
  s ? new Date(s.replace(' ', 'T')).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '';
