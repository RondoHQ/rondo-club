package club.rondo.spike;

import android.os.Bundle;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    @Override public void onCreate(Bundle savedInstanceState) {
        registerPlugin(RondoSessionVault.class);
        super.onCreate(savedInstanceState);
    }
}
