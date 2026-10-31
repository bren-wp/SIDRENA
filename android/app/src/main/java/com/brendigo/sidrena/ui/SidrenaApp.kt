package com.brendigo.sidrena.ui

import android.app.Activity
import android.content.ActivityNotFoundException
import android.content.Intent
import android.net.Uri
import android.widget.Toast
import androidx.activity.compose.BackHandler
import androidx.compose.foundation.background
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.WindowInsets
import androidx.compose.foundation.layout.fillMaxSize
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.systemBarsPadding
import androidx.compose.material3.AlertDialog
import androidx.compose.material3.Scaffold
import androidx.compose.material3.Text
import androidx.compose.material3.TextButton
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.runtime.setValue
import androidx.compose.ui.Modifier
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.unit.dp
import androidx.lifecycle.viewmodel.compose.viewModel
import com.brendigo.sidrena.SidrenaViewModel
import com.brendigo.sidrena.data.CatalogSnapshot
import com.brendigo.sidrena.data.PriceItem
import com.brendigo.sidrena.data.SiteUrl

@Composable
fun SidrenaApp(model: SidrenaViewModel = viewModel()) {
    val state = model.state
    val snapshot = state.snapshot
    val context = LocalContext.current
    var page by rememberSaveable { mutableStateOf("home") }
    var selectedItem by remember { mutableStateOf<PriceItem?>(null) }
    var detailReturnPage by rememberSaveable { mutableStateOf("products") }
    var alert by remember { mutableStateOf<String?>(null) }
    var askDisconnect by remember { mutableStateOf(false) }

    fun navigate(destination: String) {
        page = destination
        if (destination != "detail") selectedItem = null
    }

    fun openPublic(url: String) {
        val verified = runCatching {
            snapshot?.let { SiteUrl.parse(it.site).publicLink(url) }
        }.getOrNull()
        if (verified == null) {
            Toast.makeText(context, "Poveznica nije dostupna.", Toast.LENGTH_SHORT).show()
            return
        }
        try {
            context.startActivity(
                Intent(Intent.ACTION_VIEW, Uri.parse(verified)).addCategory(Intent.CATEGORY_BROWSABLE)
            )
        } catch (_: ActivityNotFoundException) {
            Toast.makeText(context, "Nema aplikacije za otvaranje poveznice.", Toast.LENGTH_SHORT).show()
        }
    }

    fun supportEmail() {
        try {
            context.startActivity(Intent(Intent.ACTION_SENDTO, Uri.parse("mailto:sidrena@brendigo.com")))
        } catch (_: ActivityNotFoundException) {
            Toast.makeText(context, "Nije pronađena aplikacija za e-poštu.", Toast.LENGTH_SHORT).show()
        }
    }

    BackHandler(enabled = page != "home" && !state.isEditingSite) {
        navigate(when (page) {
            "detail" -> detailReturnPage
            "about" -> "settings"
            "search" -> "products"
            else -> "home"
        })
    }

    if (askDisconnect) {
        AlertDialog(
            onDismissRequest = { askDisconnect = false },
            title = { Text("Odspojiti stranicu?") },
            text = { Text("Adresa će se ukloniti iz aplikacije. Podaci na WordPress stranici neće se mijenjati.") },
            confirmButton = {
                TextButton(onClick = {
                    model.disconnect()
                    askDisconnect = false
                    navigate("home")
                }) { Text("Odspoji") }
            },
            dismissButton = {
                TextButton(onClick = { askDisconnect = false }) { Text("Odustani") }
            },
        )
    }
    alert?.let { message ->
        AlertDialog(
            onDismissRequest = { alert = null },
            title = { Text("SIDRENA") },
            text = { Text(message) },
            confirmButton = { TextButton(onClick = { alert = null }) { Text("U redu") } },
        )
    }

    Scaffold(
        modifier = Modifier.fillMaxSize().background(Background).systemBarsPadding(),
        contentWindowInsets = WindowInsets(0),
        containerColor = Background,
        bottomBar = {
            if (snapshot != null && !state.isEditingSite) {
                AppNavigation(
                    active = when (page) {
                        "home", "locations" -> 0
                        "archive" -> 2
                        "settings", "about" -> 3
                        else -> 1
                    },
                    onSelect = { index ->
                        navigate(when (index) {
                            0 -> "home"
                            1 -> "products"
                            2 -> "archive"
                            else -> "settings"
                        })
                    },
                )
            }
        },
    ) { insets ->
        Column(
            Modifier.fillMaxSize().padding(insets)
                .padding(horizontal = if (state.isEditingSite || snapshot == null) 0.dp else 16.dp)
        ) {
            if (state.isEditingSite || snapshot == null) {
                ConnectionPage(
                    input = state.siteInput,
                    busy = state.isLoading,
                    error = state.error,
                    canCancel = snapshot != null,
                    onInput = model::setSiteInput,
                    onConnect = { value ->
                        navigate("home")
                        model.connect(value)
                    },
                    onCancel = {
                        if (snapshot != null) model.cancelEdit()
                        else (context as? Activity)?.finish()
                    },
                )
            } else {
                BrandHeader(onNotifications = {
                    alert = "Posljednja objava: " +
                        (snapshot.index.publishedAt ?: "Datum nije dostupan")
                })
                when (page) {
                    "home" -> HomePage(
                        data = snapshot,
                        busy = state.isLoading,
                        error = state.error,
                        onNavigate = ::navigate,
                        onRefresh = model::refresh,
                        onEdit = model::editSite,
                        onOpen = ::openPublic,
                    )
                    "products", "services" -> CatalogPage(
                        data = snapshot,
                        isService = page == "services",
                        onDetails = {
                            selectedItem = it
                            detailReturnPage = page
                            navigate("detail")
                        },
                        onSearch = { navigate("search") },
                        onSwitchKind = { navigate(if (page == "products") "services" else "products") },
                    )
                    "archive" -> ArchivePage(data = snapshot, onOpen = ::openPublic)
                    "locations" -> LocationsPage(
                        data = snapshot,
                        selectedOutlet = state.outlet,
                        onSelect = { code ->
                            model.selectOutlet(code)
                            navigate("home")
                        },
                        busy = state.isLoading,
                    )
                    "search" -> SearchPage(data = snapshot, onDetails = {
                        selectedItem = it
                        detailReturnPage = "search"
                        navigate("detail")
                    }, onBack = { navigate("products") }, onArchive = { navigate("archive") })
                    "detail" -> DetailPage(
                        item = selectedItem,
                        data = snapshot,
                        onBack = { navigate(detailReturnPage) },
                        onOpen = ::openPublic,
                    )
                    "settings" -> SettingsPage(
                        data = snapshot, busy = state.isLoading,
                        onEditSite = model::editSite,
                        onRefresh = model::refresh,
                        onDisconnect = { askDisconnect = true },
                        onAbout = { navigate("about") },
                        onHelp = { navigate("about") },
                    )
                    "about" -> AboutPage(
                        onBack = { navigate("settings") },
                        onConnectHelp = { alert = "Unesite HTTPS adresu svoje WordPress stranice s aktivnim dodatkom SIDRENA. Nakon povezivanja odaberite poslovnicu i pregledajte objavljene cjenike." },
                        onAnchorHelp = { alert = "Sidrena cijena je dokumentirana referentna vrijednost uz pripadajući datum. Uvijek provjerite podatke na izvornoj WordPress stranici." },
                        onSupport = ::supportEmail,
                    )
                    else -> HomePage(snapshot, state.isLoading, state.error,
                        ::navigate, model::refresh, model::editSite, ::openPublic)
                }
            }
        }
    }
}
