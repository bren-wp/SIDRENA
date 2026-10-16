; SIDRENA Windows Setup — packages a real, CI-tested .NET self-contained app.
; Never touch %LOCALAPPDATA%\Brendigo\SIDRENA\sidrena.db or its backups.
#define DesktopVersion "0.7.0"
[Setup]
AppId={{9A25D162-6FB4-4B48-BD20-5EE4D4766BA3}
AppName=SIDRENA
AppVersion={#DesktopVersion}
AppVerName=SIDRENA Desktop {#DesktopVersion}
AppPublisher=Brendigo
AppPublisherURL=https://brendigo.com/
AppSupportURL=https://github.com/bren-wp/SIDRENA/issues
AppUpdatesURL=https://github.com/bren-wp/SIDRENA/releases/tag/1.5.0
DefaultDirName={localappdata}\Programs\Brendigo\SIDRENA
DefaultGroupName=SIDRENA
PrivilegesRequired=lowest
UsePreviousAppDir=yes
DisableProgramGroupPage=yes
ArchitecturesAllowed=x64compatible
OutputDir=..\..\artifacts\desktop-release
OutputBaseFilename=SIDRENA-Desktop-win-x64-Setup
Compression=lzma2
SolidCompression=yes
WizardStyle=modern
UninstallDisplayName=SIDRENA Desktop
UninstallDisplayIcon={app}\SIDRENA.exe
AppMutex=Local\Brendigo.SIDRENA.Desktop
CloseApplications=yes
RestartApplications=no
SetupLogging=yes
VersionInfoVersion=0.7.0.0
VersionInfoDescription=SIDRENA Desktop Installer
VersionInfoCompany=Brendigo
VersionInfoProductName=SIDRENA Desktop
[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"
[Tasks]
Name: "desktopicon"; Description: "Stvori prečac na radnoj površini"; GroupDescription: "Dodatni prečaci:"; Flags: unchecked
[Files]
Source: "..\..\artifacts\sidrena-desktop-win-x64\*"; DestDir: "{app}"; Flags: ignoreversion recursesubdirs createallsubdirs; Excludes: "*.pdb"
[Icons]
Name: "{autoprograms}\SIDRENA"; Filename: "{app}\SIDRENA.exe"; WorkingDir: "{app}"
Name: "{autodesktop}\SIDRENA"; Filename: "{app}\SIDRENA.exe"; Tasks: desktopicon
[Run]
Filename: "{app}\SIDRENA.exe"; Description: "Pokreni SIDRENA Desktop"; Flags: nowait postinstall skipifsilent
; Deliberately no [UninstallDelete] entry: local SQLite data and backups survive uninstall.
