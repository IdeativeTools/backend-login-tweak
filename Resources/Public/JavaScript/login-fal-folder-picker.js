// noinspection NpmUsedModulesInstalled,JSFileReferences

/*
 * Opens the core folder element browser in a modal and writes the combined folder identifier
 * into the configured text field (same postMessage contract as FormEngine).
 */
import Modal from '@typo3/backend/modal.js'
import DocumentService from '@typo3/core/document-service.js'
import { MessageUtility } from '@typo3/backend/utility/message-utility.js'

DocumentService.ready().then(() => {
    document.querySelectorAll('[data-id-be-login-fal-folder-picker]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault()
            const inputId = button.getAttribute('data-input-id')
            if (!inputId) {
                return
            }
            const input = document.getElementById(inputId)
            if (!input || !(input instanceof HTMLInputElement)) {
                return
            }
            const targetWindow = window.top ?? window
            /** @var settings **/
            /** @var Wizards **/
            /** @var elementBrowserUrl **/
            const baseUrl = targetWindow.TYPO3?.settings?.Wizards?.elementBrowserUrl
            if (!baseUrl) {
                return
            }
            const fieldReference = inputId
            const params = new URLSearchParams({
                mode: 'folder',
                fieldReference
            })
            const separator = baseUrl.includes('?') ? '&' : '?'
            /** @var iframe */
            /** @var large */
            const modal = Modal.advanced({
                type: Modal.types.iframe,
                content: baseUrl + separator + params.toString(),
                size: Modal.sizes.large
            })
            const onMessage = (messageEvent) => {
                if (!MessageUtility.verifyOrigin(messageEvent.origin)) {
                    return
                }
                const data = messageEvent.data
                /** @var actionName **/
                if (data?.actionName !== 'typo3:elementBrowser:elementAdded') {
                    return
                }
                /** @var fieldName **/
                if (data.fieldName !== fieldReference) {
                    return
                }
                if (typeof data.value === 'string') {
                    input.value = data.value
                }
            }
            window.addEventListener('message', onMessage)
            modal.addEventListener(
                'typo3-modal-hide',
                () => {
                    window.removeEventListener('message', onMessage)
                },
                { once: true }
            )
        })
    })
})
