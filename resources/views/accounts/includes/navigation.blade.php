<div class="dash-nav-menu-mobile" id="mobile-nav">
  <div class="landing-nav-menu-header"><i class="material-icons toggle-mobile-nav">close</i></div>
  <ul>
    <li><a href="/inventory">Inventory</a></li>
    <li><a href="/pockets">Pockets</a></li>
    <li><a href="/auth/connectedapps">Integrations</a></li>
    <li><a href="/auth/apps">Api Keys</a></li>
  </ul>
  <hr>
  <ul>
    <li><a href="/auth/update">Settings</a></li>
    <li><a href="/auth/logout">Logout</a></li>
  </ul>
</div>
<div class="navigation">
  <div class="navigation-content">
    <div class="logo">
      <a href="/">
        <span>token<strong>pass</strong></span>
      </a>
    </div>
    <div class="user">
      <div class="avatar">
        <a href="/auth/update" title="My account">
          <img src="https://s3.amazonaws.com/{{ env('S3_BUCKET') }}/{{ hash('sha256',Auth::user()->uuid) }}/avatar.png" onerror="this.onerror=null;this.src='/img/default-avatar.png'">
        </a>
      </div>
      <i class="logout">
        <a href="/auth/logout" title="Log out">
          <svg style="width:24px;height:24px" viewBox="0 0 24 24">
            <path fill="#fff" d="M17,17.25V14H10V10H17V6.75L22.25,12L17,17.25M13,2A2,2 0 0,1 15,4V8H13V4H4V20H13V16H15V20A2,2 0 0,1 13,22H4A2,2 0 0,1 2,20V4A2,2 0 0,1 4,2H13Z"></path>
          </svg>
        </a>
      </i>
    </div>
    <div class="dash-nav-menu-btn">
      <i class="material-icons toggle-mobile-nav">
        dehaze
      </i>
    </div>
    <ul class="menu">
      <li>
        <a href="/inventory">
          <span class="linktext">Inventory</span>
        </a>
      </li>
      <li>
        <a href="/pockets">
          <span class="linktext">Pockets</span>
        </a>
      </li>
      <li>
        <a href="/auth/connectedapps">
          <span class="linktext">Integrations</span>
        </a>
      </li>
      <li>
        <a href="/auth/apps">
          <span class="linktext">Api Keys</span>
        </a>
      </li>
        
      <!-- TODO -->
      <!-- <li><a href="/dashboard"><i class="fa fa-home"></i> Accounts Home</a></li> -->
      <!-- <li><a href="/auth/update"><i class="fa fa-gears"></i> Update Account</a></li> -->
      <!-- <li><a href="/auth/apps"><i class="fa fa-code-fork"></i> API Keys / My Apps</a></li> -->
    </ul>
  </div>
</div>
