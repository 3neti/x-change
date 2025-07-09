/**
* @deprecated This component is no longer in use. Use Token.vue instead.
*/
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import axios, { AxiosResponse } from 'axios'

/**
 * Holds the newly‐created token (or null until one is generated)
 */
const token = ref<string | null>(null)

/**
 * Make sure axios will send the session cookie
 */
onMounted(() => {
    axios.defaults.withCredentials = true
})

/**
 * Call our backend `/api/token` endpoint to mint a one-off Sanctum token
 */
async function generateToken(): Promise<void> {
    try {
        const response: AxiosResponse<{ token: string }> = await axios.post(route('token.store'))
        token.value = response.data.token
        // Optionally persist for reuse:
        // localStorage.setItem('api_token', response.data.token)
    } catch (err: unknown) {
        console.error('Failed to generate token:', err)
        alert('Could not generate token. See console for details.')
    }
}
</script>

<template>
    <div class="p-4">
        <button
            @click="generateToken"
            :disabled="!!token"
            class="px-3 py-1 text-sm bg-blue-600 text-white rounded hover:bg-blue-700 disabled:opacity-60 disabled:cursor-not-allowed"
        >
            Generate API Token
        </button>

        <div v-if="token" class="mt-4 space-y-2">
<!--            <h3 class="text-base font-medium">Your API Token</h3>-->
            <pre
                class="p-1 bg-gray-100 rounded text-xs max-h-32 overflow-auto whitespace-pre-wrap break-all"
            >
  {{ token }}
</pre>
        </div>
    </div>
</template>
