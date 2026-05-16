// noinspection NpmUsedModulesInstalled,JSFileReferences

import DocumentService from '@typo3/core/document-service.js'

function updateLoginBoxPositionGridSelection () {
    const grid = document.getElementById('id-be-login-box-position-grid')
    if (!grid) {
        return
    }
    grid.querySelectorAll('.id-be-login-pos-cell').forEach((cell) => {
        const checked = cell.querySelector('input[type="radio"][name="loginBoxPosition"]:checked')
        cell.classList.toggle('id-be-login-pos-cell--selected', checked !== null)
    })
}

function ensureLoginBoxPositionRadioChecked () {
    const grid = document.getElementById('id-be-login-box-position-grid')
    const list = document.getElementById('loginBoxPositionList')
    if (!grid || !(list instanceof HTMLSelectElement)) {
        return
    }
    if (grid.querySelector('input[name="loginBoxPosition"]:checked')) {
        return
    }
    const v = list.value
    const radios = grid.querySelectorAll('input[name="loginBoxPosition"][type="radio"]')
    radios.forEach((r) => {
        if (r instanceof HTMLInputElement && r.value === v) {
            r.checked = true
        }
    })
}

DocumentService.ready().then(() => {
    const hidden = document.getElementById('id-be-login-active-tab')
    if (hidden instanceof HTMLInputElement) {
        document.addEventListener('shown.bs.tab', (event) => {
            const t = event.target
            if (!(t instanceof HTMLElement)) {
                return
            }
            const id = t.getAttribute('data-id-be-login-tab')
            if (id) {
                hidden.value = id
            }
            if (id === 'loginbox') {
                ensureLoginBoxPositionRadioChecked()
                updateLoginBoxPositionGridSelection()
            }
        })
    }

    const list = document.getElementById('loginBoxPositionList')
    const radios = document.querySelectorAll('input[name="loginBoxPosition"][type="radio"]')
    ensureLoginBoxPositionRadioChecked()
    updateLoginBoxPositionGridSelection()

    if (list instanceof HTMLSelectElement && radios.length > 0) {
        list.addEventListener('change', () => {
            const v = list.value
            radios.forEach((r) => {
                if (r instanceof HTMLInputElement && r.value === v) {
                    r.checked = true
                }
            })
            updateLoginBoxPositionGridSelection()
        })
        radios.forEach((r) => {
            r.addEventListener('change', () => {
                if (r instanceof HTMLInputElement && r.checked) {
                    list.value = r.value
                }
                updateLoginBoxPositionGridSelection()
            })
        })
    }
})
