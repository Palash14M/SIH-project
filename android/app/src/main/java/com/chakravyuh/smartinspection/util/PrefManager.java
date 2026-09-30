package com.chakravyuh.smartinspection.util;

import android.content.Context;

/**
 * PrefManager alias wrapping PreferenceManager for session and role storage.
 */
public class PrefManager extends PreferenceManager {
    public PrefManager(Context context) {
        super(context);
    }
}
