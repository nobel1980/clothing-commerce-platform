export default function StoreHomePage() {
  return (
    <div style={{ padding: '2rem', maxWidth: '1200px', margin: '0 auto' }}>
      <header style={{ marginBottom: '3rem', textAlign: 'center' }}>
        <h1 style={{ fontSize: '2.5rem', fontWeight: 700, marginBottom: '0.75rem' }}>
          Clothing Commerce Platform
        </h1>
        <p style={{ color: 'var(--muted-foreground)', fontSize: '1.125rem' }}>
          Curated apparel & modular headless architecture
        </p>
      </header>
      <section style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '1.5rem' }}>
        <div style={{ border: '1px solid var(--border)', borderRadius: '12px', padding: '1.5rem', background: 'var(--muted)' }}>
          <h2 style={{ fontSize: '1.25rem', marginBottom: '0.5rem' }}>Catalog</h2>
          <p style={{ color: 'var(--muted-foreground)' }}>Explore collections, categories, and latest drops.</p>
        </div>
        <div style={{ border: '1px solid var(--border)', borderRadius: '12px', padding: '1.5rem', background: 'var(--muted)' }}>
          <h2 style={{ fontSize: '1.25rem', marginBottom: '0.5rem' }}>Cart & Checkout</h2>
          <p style={{ color: 'var(--muted-foreground)' }}>Optimized seamless cart and modern checkout experience.</p>
        </div>
        <div style={{ border: '1px solid var(--border)', borderRadius: '12px', padding: '1.5rem', background: 'var(--muted)' }}>
          <h2 style={{ fontSize: '1.25rem', marginBottom: '0.5rem' }}>Admin Dashboard</h2>
          <p style={{ color: 'var(--muted-foreground)' }}>Complete inventory, orders, promotions, and reporting control.</p>
        </div>
      </section>
    </div>
  );
}
