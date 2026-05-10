import { ref } from 'vue';

/**
 * Composable for copying text to clipboard with fallback support
 * 
 * @param timeout - How long to show "copied" state (ms)
 */
export function useClipboard(timeout = 2000) {
    const copiedText = ref<string | null>(null);
    const error = ref<string | null>(null);

    /**
     * Copy text to clipboard with automatic fallback
     * Works in both secure (HTTPS) and non-secure contexts
     */
    const copy = async (text: string): Promise<boolean> => {
        error.value = null;

        try {
            // Try modern Clipboard API first (requires secure context)
            if (navigator.clipboard && navigator.clipboard.writeText) {
                await navigator.clipboard.writeText(text);
                copiedText.value = text;
                
                // Reset after timeout
                setTimeout(() => {
                    copiedText.value = null;
                }, timeout);
                
                return true;
            }
            
            // Fallback for non-secure contexts (http://, .test domains)
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    copiedText.value = text;
                    
                    // Reset after timeout
                    setTimeout(() => {
                        copiedText.value = null;
                    }, timeout);
                }
                return successful;
            } finally {
                document.body.removeChild(textArea);
            }
        } catch (err) {
            error.value = err instanceof Error ? err.message : 'Failed to copy';
            console.error('Failed to copy text:', err);
            return false;
        }
    };

    /**
     * Check if a specific text is currently marked as copied
     */
    const isCopied = (text: string): boolean => {
        return copiedText.value === text;
    };

    /**
     * Clear the copied state manually
     */
    const clear = () => {
        copiedText.value = null;
        error.value = null;
    };

    return {
        copy,
        copiedText,
        isCopied,
        error,
        clear,
    };
}
