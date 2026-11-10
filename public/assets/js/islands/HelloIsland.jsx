import { useState } from 'react';

export default function HelloIsland({ label = 'React island' }) {
  const [count, setCount] = useState(0);
  return (
    <div style={{ display: 'inline-flex', alignItems: 'center', gap: '0.5rem', padding: '0.4rem 0.8rem', borderRadius: '999px', background: 'var(--primary-tint)', color: 'var(--primary)', fontSize: '0.85rem', fontWeight: 600 }}>
      <span>{label}</span>
      <button type="button" onClick={() => setCount((c) => c + 1)} style={{ background: 'transparent', border: '1px solid currentColor', borderRadius: '999px', padding: '0 0.5rem', cursor: 'pointer', color: 'inherit' }}>
        {count}
      </button>
    </div>
  );
}
