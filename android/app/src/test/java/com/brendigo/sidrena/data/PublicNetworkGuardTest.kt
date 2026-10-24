package com.brendigo.sidrena.data

import org.junit.Assert.assertFalse
import org.junit.Assert.assertTrue
import org.junit.Test
import java.net.InetAddress

class PublicNetworkGuardTest {
    @Test fun privateAndDocumentationAddressesAreRejected() {
        for (address in listOf("127.0.0.1","10.0.0.1","192.168.1.1",
            "172.20.5.4","100.64.1.1","169.254.5.1","198.18.0.1",
            "192.0.2.4","198.51.100.9","203.0.113.2","::1","2001:db8::1")) {
            assertFalse(address,PublicNetworkGuard.isPublic(InetAddress.getByName(address)))
        }
    }

    @Test fun ordinaryPublicAddressIsAccepted() {
        assertTrue(PublicNetworkGuard.isPublic(InetAddress.getByName("1.1.1.1")))
        assertTrue(PublicNetworkGuard.isPublic(InetAddress.getByName("2606:4700:4700::1111")))
    }
}
