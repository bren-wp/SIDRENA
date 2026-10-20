package com.brendigo.sidrena.data

import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

class PriceDisplayTest {
    @Test fun formatsWordPressDecimalForCroatianLocale() {
        assertTrue(PriceDisplay.euro("1.09").contains("1,09"))
        assertTrue(PriceDisplay.euro("1,09").contains("1,09"))
        assertTrue(PriceDisplay.euro("1.234,50").contains("1.234,50"))
    }

    @Test fun missingPriceNeverBecomesZero() {
        assertEquals("—", PriceDisplay.euro(""))
        assertTrue(PriceDisplay.euro("nepoznato").contains("nepoznato"))
    }
}
