package createConfig;

import java.io.IOException;
import java.util.Iterator;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.nio.file.Files;
import java.util.Properties;
import java.io.BufferedReader;
import java.io.StringReader;

public class Config extends Properties {

static final String COMMENTER = ";";	//default comment indicator

public Config (String configName) throws IOException {
	this(configName, Config.COMMENTER);
}

public Config (String configName, String commenter) throws IOException {
	this(Paths.get(configName), commenter);
}

public Config (Path configFile) throws IOException {
	this(configFile, Config.COMMENTER);
}

public Config (Path configFile, String commenter) throws IOException {
	BufferedReader configIn = null;
	String aBuffer;
	Config listMore;
	Iterator<String> iS;
	int I;
	String fullPath = configFile.getParent() + "/";

  try {
	configIn = Files.newBufferedReader(configFile);
//Remove comments and iterate thru _MORE[] files:
	while ((aBuffer = configIn.readLine()) != null) {
		I = aBuffer.indexOf(commenter);
		if (I > -1) {
			aBuffer = aBuffer.substring(0, I);
		}
		aBuffer = aBuffer.trim();
		if (aBuffer.isEmpty()) continue; //a blank line
		this.load(new StringReader(aBuffer));
		if (aBuffer.startsWith("_MORE[]")) {	//parse & load the _MORE[]= line
			listMore = new Config(fullPath + this.getProperty("_MORE[]"), commenter);
			this.remove("_MORE[]");	//remove the MORE[] element
			iS = listMore.stringPropertyNames().iterator();
			while (iS.hasNext()) {	//add the _MORE[] elements to our Properties
				aBuffer = iS.next();
				this.load(new StringReader(aBuffer + "=" + listMore.getProperty(aBuffer)));
			}
		}
	}
  } catch (IOException IOe) {
	System.err.println("Error on Config " + configFile.toString() + ": " + IOe.getMessage());
//	IOe.printStackTrace();
  } finally {
	if (configIn != null) {
		configIn.close();
		configIn = null;
	}
  } // end try
}

public void listConfig() {
	Iterator<String> iS;
	String aBuffer;

	iS = this.stringPropertyNames().iterator();
	System.out.println("Properties:");
	while (iS.hasNext()) {
		aBuffer = iS.next();
		System.out.println(aBuffer + "=" + this.getProperty(aBuffer) + ";");
	}
}

} // end class Config

