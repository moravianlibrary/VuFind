{js filename="ajax_common.js"}
{js filename="search.js"}

{* Main Listing *}
<div id="bd">
  <div id="yui-main" class="content">
    <div class="yui-b first">
    <b class="btop"><b></b></b>


      {* Recommendations *}
      {if $topRecommendations}
        {foreach from=$topRecommendations item="recommendations"}
          {include file=$recommendations}
        {/foreach}
      {/if}

      {* Listing Options *}
      <div class="yui-ge resulthead">
        <div class="yui-u first">
        {if $recordCount}
          <p>{translate text="Showing"}
          <b>{$recordStart}</b> - <b>{$recordEnd}</b>
          {translate text='of'} <b>{$recordCount}</b>
          {if $searchType == 'basic'}{translate text='for search'}: <b>'{$lookfor|escape:"html"}'</b>,{/if}
        {/if}
          {translate text='query time'}: {$qtime}s</p>
          {if $spellingSuggestions}
          <div class="correction">
          <p class="head">{translate text='spell_suggest'}:</p>
          {foreach from=$spellingSuggestions item=details key=term name=termLoop}
            <span class="label">{$term|escape} &raquo; {foreach from=$details.suggestions item=data key=word name=suggestLoop}</span>
            <ul>
              <li><a href="{$data.replace_url|escape}">{$word|escape}</a></li>
              <li>{if $data.expand_url} <a href="{$data.expand_url|escape}"><img src="{$path}/images/silk/expand.png" alt="{translate text='spell_expand_alt'}"/></a>{if !$smarty.foreach.suggestLoop.last}, {/if}{/if}</li>
            </ul>
                          
          {/foreach}
              {if !$smarty.foreach.termLoop.last}<br/>{/if}
          {/foreach}
          </div>
          {/if}
        </div>

        <!-- <div>  --> <!-- class="yui-u toggle" -->
          <table>
          <tr align="left">
          {if $limitList|@count gt 1}
           <td>
           <form action="{$path}/Search/LimitResults" method="post">
            <label for="limit">{translate text='Results per page'}</label>
            <select id="limit" name="limit" onChange="document.location.href = this.options[this.selectedIndex].value;">
              {foreach from=$limitList item=limitData key=limitLabel}
                <option value="{$limitData.limitUrl|escape}"{if $limitData.selected} selected="selected"{/if}>{$limitData.desc|escape}</option>
              {/foreach}
            </select>
            <noscript><input type="submit" value="{translate text="Set"}" /></noscript>
          </form>
          </td>
          {/if}
          <td>
          <form action="{$path}/Search/SortResults" method="post">
            <label for="sort">{translate text='Sort'}</label>
            <select id="sort" name="sort" onChange="document.location.href = this.options[this.selectedIndex].value;">
              {foreach from=$sortList item=sortData key=sortLabel}
                <option value="{$sortData.sortUrl|escape}"{if $sortData.selected} selected{/if}>{translate text=$sortData.desc}</option>
              {/foreach}
            </select>
            <noscript><input type="submit" value="{translate text="Set"}" /></noscript>
          </form>
         </td>
         <td>
          {if $viewList|@count gt 1}
            {foreach from=$viewList item=viewData key=viewLabel}
              &nbsp;
              {if !$viewData.selected}<a href="{$viewData.viewUrl|escape}" title="{translate text='Switch view to'} {translate text=$viewData.desc}"  style="text-decoration:none">{/if}
              {image src="view_`$viewData.viewType`.png"}
              {if !$viewData.selected}</a>{/if}&nbsp;
            {/foreach}
            <br />
          {/if}
         </td>
         </tr>
         </table>
         <form method="post" name="bulkActionForm" action="{$url}/Cart/Home">
           <div class="searchtools">
             <input type="checkbox" class="selectAllCheckboxes floatleft" name="selectAll" id="addFormCheckboxSelectAll"/>
             <label class="floatleft" for="addFormCheckboxSelectAll">{translate text="select_page"}</label>
             {*<strong>{translate text='Search Tools'}:</strong>*}
             {if $savedSearch}
               <a href="{$url}/MyResearch/SaveSearch?delete={$searchId}" class="delete">{translate text='save_search_remove'}</a>
             {else}
               <a href="{$url}/MyResearch/SaveSearch?save={$searchId}" class="add">{translate text='save_search'}</a>
             {/if}
             <a id="searchHistory" href="{$path}/Search/History">{translate text='Search History'}</a>
             <a href="{$rssLink|escape}" class="feed">{translate text='Get RSS Feed'}</a>
             <a href="{$url}/Search/Email" class="mail" onClick="getLightbox('Search', 'Email', null, null, '{translate text="Email this"}'); return false;">{translate text='Email this Search'}</a>
           </div>
         </form>
        <!-- </div> -->
       
      </div>
      {* End Listing Options *}

      {if $subpage}
        {include file=$subpage}
      {else}
        {$pageContent}
      {/if}

      {if $pageLinks.all}<div class="pagination">{$pageLinks.all}</div>{/if}
      {*
      <div class="searchtools">
        <strong>{translate text='Search Tools'}:</strong>
        <a href="{$rssLink|escape}" class="feed">{translate text='Get RSS Feed'}</a>
        <a href="{$url}/Search/Email" class="mail" onClick="getLightbox('Search', 'Email', null, null, '{translate text="Email this"}'); return false;">{translate text='Email this Search'}</a>
        {if $savedSearch}<a href="{$url}/MyResearch/SaveSearch?delete={$searchId}" class="delete">{translate text='save_search_remove'}</a>{else}<a href="{$url}/MyResearch/SaveSearch?save={$searchId}" class="add">{translate text='save_search'}</a>{/if}
      </div>
      *}
      
      <b class="bbot"><b></b></b>
    </div>
    {* End Main Listing *}
    
  </div>

  {* Narrow Search Options *}
  <div class="yui-b">
    {if $sideRecommendations}
      {foreach from=$sideRecommendations item="recommendations"}
        {include file=$recommendations}
      {/foreach}
    {/if}
  </div>
  {* End Narrow Search Options *}

</div>
{if $showPreviews}
<script type="text/javascript">
{if $showGBSPreviews}
document.write(unescape("%3Cscript src=http://books.google.com/books?jscmd=viewapi&bibkeys=" + doGetExtIds() + "&callback=ProcessGBSBookInfo" + " type='text/javascript'%3E%3C/script%3E"));
{/if}
{if $showOLPreviews}
document.write(unescape("%3Cscript src=http://openlibrary.org/api/books?bibkeys=" + doGetExtIds() + "&callback=ProcessOLBookInfo" + " type='text/javascript'%3E%3C/script%3E"));
{/if}
{if $showHTPreviews}
document.write(unescape("%3Cscript src=http://catalog.hathitrust.org/api/volumes/brief/json/" + doGetHTIds() + "&callback=ProcessHTBookInfo" + " type='text/javascript'%3E%3C/script%3E"));
{/if}
</script>
{/if}
