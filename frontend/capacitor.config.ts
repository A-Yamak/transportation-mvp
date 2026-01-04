import type { CapacitorConfig } from '@capacitor/cli'

const config: CapacitorConfig = {
  appId: 'com.yourcompany.transportationapp',
  appName: 'Transportation App',
  webDir: 'dist',
  server: {
    // For development, connect to your local API
    // Remove or change this for production
    url: 'http://10.0.2.2:5173', // Android emulator localhost
    cleartext: true,
  },
  plugins: {
    GoogleMaps: {
      // Add your Google Maps API key here
      // apiKey: 'YOUR_GOOGLE_MAPS_API_KEY',
    },
    PushNotifications: {
      presentationOptions: ['badge', 'sound', 'alert'],
    },
    Geolocation: {
      // Plugin configuration
    },
  },
  android: {
    allowMixedContent: true,
  },
  ios: {
    // iOS specific configuration
  },
}

export default config
