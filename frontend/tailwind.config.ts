import type { Config } from 'tailwindcss'

const config: Config = {
  content: ['./index.html', './src/**/*.{js,ts,jsx,tsx}'],
  theme: {
    extend: {
      colors: {
        brand: {
          navy: '#0f172a',
          blue: '#0ea5e9',
          teal: '#14b8a6',
          bg: '#f8fafc',
          orange: '#ea580c',
        },
      },
    },
  },
  plugins: [],
}

export default config
