import { onBeforeUnmount, onMounted, shallowRef } from 'vue'

/**
 * The page's main "add" action, for the shell's round + button (phone dock). A view registers it on
 * mount and it's cleared on unmount, so the button appears only where there's something to add.
 * `label` is an i18n key for the button's aria-label.
 */
export const addAction = shallowRef(null)

export function useAddAction(run, label) {
  const entry = { run, label }
  onMounted(() => (addAction.value = entry))
  // Cleared a moment later: pages swap out-in, so the next page registers its own action first and the
  // button stays put instead of popping out and back in between two pages that both have one.
  onBeforeUnmount(() =>
    setTimeout(() => {
      if (addAction.value === entry) addAction.value = null
    }, 60),
  )
}
