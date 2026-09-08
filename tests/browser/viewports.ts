export interface ViewportConfig {
    name: string;
    width: number;
    height: number;
    deviceScaleFactor?: number;
    isMobile?: boolean;
    hasTouch?: boolean;
}

/**
 * Standard Viewport Matrix based on Wholesale Distribution Frontend Specification.
 */
export const VIEWPORT_MATRIX: Record<string, ViewportConfig> = {
    // Mobile Breakpoints
    'mobile_s_320': {
        name: 'Mobile S (320px)',
        width: 320,
        height: 568,
        isMobile: true,
        hasTouch: true,
    },
    'mobile_m_375': {
        name: 'Mobile M (375px)',
        width: 375,
        height: 667,
        isMobile: true,
        hasTouch: true,
    },
    'mobile_standard_390': {
        name: 'Mobile Standard iPhone (390px)',
        width: 390,
        height: 844,
        isMobile: true,
        hasTouch: true,
    },
    'mobile_max_430': {
        name: 'Mobile Max (430px)',
        width: 430,
        height: 932,
        isMobile: true,
        hasTouch: true,
    },

    // Tablet Breakpoints
    'small_tablet_640': {
        name: 'Landscape Mobile / Small Tablet (640px)',
        width: 640,
        height: 800,
        isMobile: true,
        hasTouch: true,
    },
    'tablet_portrait_768': {
        name: 'Tablet Portrait iPad (768px)',
        width: 768,
        height: 1024,
        isMobile: false,
        hasTouch: true,
    },
    'tablet_air_820': {
        name: 'Tablet iPad Air (820px)',
        width: 820,
        height: 1180,
        isMobile: false,
        hasTouch: true,
    },

    // Desktop Breakpoints
    'desktop_standard_1024': {
        name: 'Desktop Standard (1024px)',
        width: 1024,
        height: 768,
        isMobile: false,
        hasTouch: false,
    },
    'desktop_large_1280': {
        name: 'Desktop Large / Laptop (1280px)',
        width: 1280,
        height: 800,
        isMobile: false,
        hasTouch: false,
    },
    'desktop_xl_1440': {
        name: 'Desktop XL (1440px)',
        width: 1440,
        height: 900,
        isMobile: false,
        hasTouch: false,
    },
    'desktop_fhd_1920': {
        name: 'Desktop Full HD (1920px)',
        width: 1920,
        height: 1080,
        isMobile: false,
        hasTouch: false,
    },
};

/**
 * Convenient presets for common testing scenarios.
 */
export const VIEWPORT_PRESETS = {
    mobile: VIEWPORT_MATRIX['mobile_standard_390'],
    tablet: VIEWPORT_MATRIX['tablet_portrait_768'],
    desktop: VIEWPORT_MATRIX['desktop_xl_1440'],
    desktop_fhd: VIEWPORT_MATRIX['desktop_fhd_1920'],
};
