import { createRoot } from 'react-dom/client';

// Island registry: data-island="name" on a container mounts the matching component.
const islands = {
  'hello': () => import('./HelloIsland.jsx').then((m) => m.default),
};

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-island]').forEach(async (el) => {
    const name = el.dataset.island;
    const loader = islands[name];
    if (!loader) return;
    const Component = await loader();
    createRoot(el).render(<Component {...JSON.parse(el.dataset.props || '{}')} />);
  });
});
