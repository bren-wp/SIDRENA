package com.brendigo.sidrena

import android.app.Application
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.setValue
import androidx.lifecycle.AndroidViewModel
import androidx.lifecycle.viewModelScope
import com.brendigo.sidrena.data.CatalogRepository
import com.brendigo.sidrena.data.CatalogSnapshot
import com.brendigo.sidrena.data.SiteUrl
import kotlinx.coroutines.CancellationException
import kotlinx.coroutines.launch

data class SidrenaState(
    val siteInput: String = "",
    val snapshot: CatalogSnapshot? = null,
    val outlet: String = "",
    val isLoading: Boolean = false,
    val isEditingSite: Boolean = true,
    val error: String? = null,
)

class SidrenaViewModel(application: Application) : AndroidViewModel(application) {
    private val preferences = application.getSharedPreferences("sidrena_public_site", 0)
    private val repository = CatalogRepository()
    var state by mutableStateOf(SidrenaState())
        private set
    private var requestId = 0L

    init {
        val saved = preferences.getString("site", "").orEmpty()
        if (saved.isNotBlank()) connect(saved)
    }

    fun setSiteInput(value: String) {
        state = state.copy(siteInput = value, error = null)
    }

    fun editSite() {
        requestId++
        state = state.copy(isEditingSite = true, isLoading = false, error = null)
    }

    fun cancelEdit() {
        requestId++
        val snapshot = state.snapshot ?: return
        state = state.copy(isEditingSite = false, isLoading = false,
            siteInput = snapshot.site, error = null)
    }

    fun disconnect() {
        requestId++
        preferences.edit().remove("site").apply()
        state = SidrenaState()
    }

    fun connect(raw: String = state.siteInput) {
        val site = try { SiteUrl.parse(raw).display } catch (exception: IllegalArgumentException) {
            state = state.copy(siteInput = raw, isEditingSite = true, error = exception.message)
            return
        }
        load(site, "", true)
    }

    fun selectOutlet(code: String) {
        val site = state.snapshot?.site ?: return
        if (code == state.outlet) return
        load(site, code, false)
    }

    fun refresh() {
        val site = state.snapshot?.site ?: return
        load(site, state.outlet, false)
    }

    private fun load(site: String, outlet: String, newSite: Boolean) {
        val id = ++requestId
        state = state.copy(siteInput = site, isLoading = true, error = null,
            isEditingSite = if (newSite) true else state.isEditingSite)
        viewModelScope.launch {
            try {
                val loaded = repository.load(site, outlet)
                if (id != requestId) return@launch
                preferences.edit().putString("site", loaded.site).apply()
                state = state.copy(siteInput = loaded.site, snapshot = loaded,
                    outlet = outlet, isEditingSite = false, isLoading = false, error = null)
            } catch (cancel: CancellationException) {
                throw cancel
            } catch (_: Exception) {
                if (id != requestId) return@launch
                state = state.copy(isLoading = false,
                    error = "Povezivanje nije uspjelo. Provjerite adresu, mrežu i objavljene cjenike.")
            }
        }
    }
}
