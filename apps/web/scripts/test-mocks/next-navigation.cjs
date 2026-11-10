module.exports = {
  useRouter: () => ({ push: () => {}, replace: () => {}, refresh: () => {} }),
  usePathname: () => '/products',
  useSearchParams: () => new URLSearchParams(),
};

