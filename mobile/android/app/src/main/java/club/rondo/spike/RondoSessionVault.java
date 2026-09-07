package club.rondo.spike;

import android.security.keystore.KeyGenParameterSpec;
import android.security.keystore.KeyProperties;
import android.util.AtomicFile;
import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import java.io.File;
import java.io.FileOutputStream;
import java.nio.ByteBuffer;
import java.nio.charset.StandardCharsets;
import java.security.KeyStore;
import javax.crypto.Cipher;
import javax.crypto.KeyGenerator;
import javax.crypto.SecretKey;
import javax.crypto.spec.GCMParameterSpec;

@CapacitorPlugin(name = "RondoSessionVault")
public class RondoSessionVault extends Plugin {
    private static final String ALIAS = "club.rondo.spike.session";
    private AtomicFile file() {
        // Excluded from both cloud backup and device-to-device transfer.
        return new AtomicFile(new File(getContext().getNoBackupFilesDir(), "rondo-session"));
    }
    private SecretKey key(boolean create) throws Exception {
        KeyStore store = KeyStore.getInstance("AndroidKeyStore");
        store.load(null);
        if (!store.containsAlias(ALIAS)) {
            if (!create) throw new IllegalStateException("Missing key");
            KeyGenerator generator = KeyGenerator.getInstance(KeyProperties.KEY_ALGORITHM_AES, "AndroidKeyStore");
            generator.init(new KeyGenParameterSpec.Builder(ALIAS, KeyProperties.PURPOSE_ENCRYPT | KeyProperties.PURPOSE_DECRYPT)
                .setBlockModes(KeyProperties.BLOCK_MODE_GCM).setEncryptionPaddings(KeyProperties.ENCRYPTION_PADDING_NONE)
                .setKeySize(256).setRandomizedEncryptionRequired(true).build());
            generator.generateKey();
        }
        return (SecretKey) store.getKey(ALIAS, null);
    }
    @PluginMethod public synchronized void read(PluginCall call) {
        try {
            JSObject result = new JSObject();
            if (!file().getBaseFile().exists()) { result.put("value", org.json.JSONObject.NULL); call.resolve(result); return; }
            byte[] stored = file().readFully();
            if (stored.length < 29 || stored.length > 8300) throw new IllegalStateException("Invalid record");
            ByteBuffer buffer = ByteBuffer.wrap(stored);
            byte[] iv = new byte[12]; buffer.get(iv);
            byte[] encrypted = new byte[buffer.remaining()]; buffer.get(encrypted);
            Cipher cipher = Cipher.getInstance("AES/GCM/NoPadding");
            cipher.init(Cipher.DECRYPT_MODE, key(false), new GCMParameterSpec(128, iv));
            result.put("value", new String(cipher.doFinal(encrypted), StandardCharsets.UTF_8));
            call.resolve(result);
        } catch (Exception error) { call.reject("Beveiligde opslag is niet beschikbaar."); }
    }
    @PluginMethod public synchronized void write(PluginCall call) {
        FileOutputStream output = null;
        AtomicFile target = file();
        try {
            String value = call.getString("value");
            if (value == null || value.getBytes(StandardCharsets.UTF_8).length > 8192) throw new IllegalArgumentException();
            Cipher cipher = Cipher.getInstance("AES/GCM/NoPadding");
            cipher.init(Cipher.ENCRYPT_MODE, key(true));
            byte[] encrypted = cipher.doFinal(value.getBytes(StandardCharsets.UTF_8));
            output = target.startWrite();
            output.write(cipher.getIV()); output.write(encrypted);
            target.finishWrite(output);
            call.resolve();
        } catch (Exception error) {
            if (output != null) target.failWrite(output);
            call.reject("De sessie kon niet veilig worden opgeslagen.");
        }
    }
    @PluginMethod public synchronized void clear(PluginCall call) {
        file().delete();
        if (file().getBaseFile().exists()) call.reject("De opgeslagen sessie kon niet worden verwijderd.");
        else call.resolve();
    }
}
