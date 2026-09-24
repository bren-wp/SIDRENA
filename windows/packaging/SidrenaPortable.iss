#define AppVersion GetEnv('SIDRENA_VERSION')
#define SourceDir GetEnv('SIDRENA_PUBLISH_DIR')
#define OutputDir GetEnv('SIDRENA_WINDOWS_ARTIFACTS')

[Setup]
AppId={{8C0B26C6-A93B-43CB-9C8B-D1C1D42E4D7A}
AppName=Sidrena Desktop Portable
AppVersion={#AppVersion}
AppPublisher=Brendigo
AppPublisherURL=https://brendigo.com/
AppSupportURL=https://sidrene-cijene.com.hr/
DefaultDirName={userdocs}\Sidrena Desktop Portable
DefaultGroupName=Sidrena Desktop Portable
DisableProgramGroupPage=yes
DisableReadyMemo=yes
OutputDir={#OutputDir}
OutputBaseFilename=portable
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
PrivilegesRequired=lowest
ArchitecturesAllowed=x64
ArchitecturesInstallIn64BitMode=x64
CreateUninstallRegKey=no
Uninstallable=no

[Languages]
Name: "croatian"; MessagesFile: "compiler:Languages\Croatian.isl"
Name: "english"; MessagesFile: "compiler:Default.isl"

[Files]
Source: "{#SourceDir}\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs

[Icons]
Name: "{group}\Sidrena Desktop Portable"; Filename: "{app}\Sidrena.Windows.exe"

[Run]
Filename: "{app}\Sidrena.Windows.exe"; Description: "Launch Sidrena Desktop Portable"; Flags: nowait postinstall skipifsilent
