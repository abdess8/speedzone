import Vuex from 'vuex';

import layout, { STORAGE_KEY } from './modules/layout';
import notification from './modules/layout';
import todo from './modules/todo';

// Persist the Theme Customizer (layout) state to localStorage on every change
const persistLayout = (store) => {
  store.subscribe((mutation, state) => {
    if (mutation.type.startsWith('layout/')) {
      try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(state.layout));
      } catch (e) {
        // Ignore storage errors (e.g. private mode / quota exceeded)
      }
    }
  });
};

const store = new Vuex.Store({
  modules: {
    layout: layout, // Register the layout module
    notification, // Register the notifications module
    todo

    // Add more modules as needed
  },
  plugins: [persistLayout],
});

export default store;

