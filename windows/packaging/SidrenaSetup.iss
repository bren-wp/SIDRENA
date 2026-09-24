#define AppVersion GetEnv('SIDRENA_VERSION')
#define SourceDir GetEnv('SIDRENA_PUBLISH_DIR')
#define OutputDir GetEnv('SIDRENA_WINDOWS_ARTIFACTS')

[Setup]
AppId={{0D734305-68E9-44E4-97E7-52EF724DD2DE}
AppName=Sidrena Desktop
AppVersion={#AppVersion}
AppPublisher=Brendigo
AppPublisherURL=https://brendigo.com/
AppSupportURL=https://sidrene-cijene.com.hr/
AppUpdatesURL=https://sidrene-cijene.com.hr/
DefaultDirName={autopf}\Sidrena Desktop
DefaultGroupName=Sidrena Desktop
DisableProgramGroupPage=yes
OutputDir={#OutputDir}
OutputBaseFilename=setup
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
PrivilegesRequired=lowest
ArchitecturesAllowed=x64
ArchitecturesInstallIn64BitMode=x64
UninstallDisplayName=Sidrena Desktop
SetupIconFile=

[Languages]
Name: "croatian"; MessagesFile: "compiler:Languages\Croatian.isl"
Name: "english"; MessagesFile: "compiler:Default.isl"

[Files]
Source: "{#SourceDir}\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs

[Icons]
Name: "{group}\Sidrena Desktop"; Filename: "{app}\Sidrena.Windows.exe"
Name: "{autodesktop}\Sidrena Desktop"; Filename: "{app}\Sidrena.Windows.exe"; Tasks: desktopicon

[Tasks]
Name: "desktopicon"; Description: "Create a desktop shortcut"; GroupDescription: "Additional shortcuts:"; Flags: unchecked

[Run]
Filename: "{app}\Sidrena.Windows.exe"; Description: "Launch Sidrena Desktop"; Flags: nowait postinstall skipifsilent
